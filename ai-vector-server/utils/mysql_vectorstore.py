import mysql.connector
from mysql.connector import Error
import json
import numpy as np
import time
from typing import List, Dict, Any, Optional
from config import MYSQL_CONFIG

class MySQLVectorStore:
    def __init__(self, embedding_function, collection_name: str = "default"):
        self.embedding_function = embedding_function
        self.collection_name = collection_name
        self.connection = None
        self._connect()
        self._create_tables()
    
    def _connect(self):
        """Kết nối đến MySQL database"""
        try:
            self.connection = mysql.connector.connect(
                host=MYSQL_CONFIG["host"],
                port=MYSQL_CONFIG["port"],
                database=MYSQL_CONFIG["database"],
                user=MYSQL_CONFIG["user"],
                password=MYSQL_CONFIG["password"]
            )
            if self.connection.is_connected():
                print(f"Đã kết nối MySQL database: {MYSQL_CONFIG['database']}")
        except Error as e:
            print(f"Lỗi kết nối MySQL: {e}")
            raise e
    
    def _create_tables(self):
        """Tạo bảng lưu trữ vectors nếu chưa tồn tại"""
        cursor = self.connection.cursor()
        
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS vector_collections (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) UNIQUE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        """)
        
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS vector_documents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                collection_id INT NOT NULL,
                content LONGTEXT NOT NULL,
                embedding VECTOR(768) NOT NULL,
                metadata JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (collection_id) REFERENCES vector_collections(id) ON DELETE CASCADE,
                INDEX idx_collection_id (collection_id)
            )
        """)
        
        self.connection.commit()
        cursor.close()

    def _vector_to_string(self, vector: List[float]) -> str:
        """Chuyển đổi list/array thành string format cho MySQL VECTOR"""
        # MySQL VECTOR type yêu cầu format: "[val1,val2,val3,...]"
        vector_str = "[" + ",".join(str(float(v)) for v in vector) + "]"
        return vector_str
    
    def _get_or_create_collection(self, collection_name: str) -> int:
        """Lấy hoặc tạo collection, trả về collection_id"""
        cursor = self.connection.cursor()
        
        cursor.execute(
            "SELECT id FROM vector_collections WHERE name = %s",
            (collection_name,)
        )
        result = cursor.fetchone()
        
        if result:
            collection_id = result[0]
        else:
            cursor.execute(
                "INSERT INTO vector_collections (name) VALUES (%s)",
                (collection_name,)
            )
            self.connection.commit()
            collection_id = cursor.lastrowid
        
        cursor.close()
        return collection_id
    
    def delete_collection(self, collection_name: str):
        """Xóa collection và tất cả documents của nó"""
        cursor = self.connection.cursor()
        
        cursor.execute(
            "DELETE FROM vector_collections WHERE name = %s",
            (collection_name,)
        )
        self.connection.commit()
        cursor.close()
        print(f"Đã xóa collection: {collection_name}")
    
    def truncate_all_data(self):
        """Xóa toàn bộ dữ liệu trong tất cả các bảng vector"""
        cursor = self.connection.cursor()

        t0 = time.perf_counter()
        cursor.execute("SET FOREIGN_KEY_CHECKS = 0")
        cursor.execute("TRUNCATE TABLE vector_documents")
        cursor.execute("TRUNCATE TABLE vector_collections")
        cursor.execute("SET FOREIGN_KEY_CHECKS = 1")

        self.connection.commit()
        cursor.close()
        elapsed = time.perf_counter() - t0
        print(f"Đã xóa toàn bộ dữ liệu vector trong database (elapsed: {elapsed:.3f}s)")
    
    def add_documents(self, documents: List[Any], collection_name: Optional[str] = None):
        """Thêm documents vào collection"""
        if collection_name is None:
            collection_name = self.collection_name
        
        collection_id = self._get_or_create_collection(collection_name)
        cursor = self.connection.cursor()
        
        contents = [getattr(doc, 'page_content', '') for doc in documents]
        metadata_list = [getattr(doc, 'metadata', None) for doc in documents]

        t_start = time.perf_counter()
        embedding_time = 0.0
        insert_time = 0.0

        if hasattr(self.embedding_function, 'embed_documents'):
            t_e0 = time.perf_counter()
            try:
                embeddings = self.embedding_function.embed_documents(contents)
            except Exception:
                embeddings = []
                for c in contents:
                    te0 = time.perf_counter()
                    embeddings.append(self.embedding_function.embed_query(c))
                    embedding_time += time.perf_counter() - te0
            else:
                embedding_time += time.perf_counter() - t_e0
        else:
            embeddings = []
            for c in contents:
                te0 = time.perf_counter()
                embeddings.append(self.embedding_function.embed_query(c))
                embedding_time += time.perf_counter() - te0

        for emb, content, meta in zip(embeddings, contents, metadata_list):
            ti0 = time.perf_counter()
            embedding_str = self._vector_to_string(emb)
            metadata_json = json.dumps(meta) if meta else None
            cursor.execute(
                """
                INSERT INTO vector_documents (collection_id, content, embedding, metadata)
                VALUES (%s, %s, STRING_TO_VECTOR(%s), %s)
                """,
                (collection_id, content, embedding_str, metadata_json)
            )
            insert_time += time.perf_counter() - ti0
        
        self.connection.commit()
        cursor.close()
        total_elapsed = time.perf_counter() - t_start
        print(f"Đã thêm {len(documents)} documents vào collection: {collection_name}")
        print(f"Timings: total={total_elapsed:.3f}s, embed={embedding_time:.3f}s, insert={insert_time:.3f}s")
    
    def similarity_search(self, query: str, k: int = 4, collection_name: Optional[str] = None) -> List[Dict]:
        """Tìm kiếm documents tương tự dựa trên cosine similarity"""
        if collection_name is None:
            collection_name = self.collection_name
        
        query_embedding = self.embedding_function.embed_query(query)
        
        cursor = self.connection.cursor(dictionary=True)
        
        # Lấy tất cả documents từ collection (không dùng COSINE_DISTANCE vì không có trong MySQL 9.5)
        cursor.execute(
            """
            SELECT 
                vd.id, 
                vd.content, 
                vd.embedding,
                vd.metadata
            FROM vector_documents vd
            JOIN vector_collections vc ON vd.collection_id = vc.id
            WHERE vc.name = %s
            """,
            (collection_name,)
        )
        
        results = cursor.fetchall()
        cursor.close()
        
        if not results:
            return []
        
        similarities = []
        for row in results:
            emb_raw = row['embedding']
            if isinstance(emb_raw, bytes):
                doc_embedding = json.loads(emb_raw.decode('utf-8'))
            elif isinstance(emb_raw, str):
                doc_embedding = json.loads(emb_raw)
            else:
                doc_embedding = emb_raw
            
            similarity = self._cosine_similarity(query_embedding, doc_embedding)
            similarities.append({
                'id': row['id'],
                'content': row['content'],
                'metadata': json.loads(row['metadata']) if row['metadata'] else {},
                'similarity': float(similarity)
            })
        
        similarities.sort(key=lambda x: x['similarity'], reverse=True)
        return similarities[:k]
    
    def _cosine_similarity(self, vec1: List[float], vec2: List[float]) -> float:
        """Tính cosine similarity giữa 2 vectors"""
        vec1 = np.array(vec1)
        vec2 = np.array(vec2)
        
        dot_product = np.dot(vec1, vec2)
        norm1 = np.linalg.norm(vec1)
        norm2 = np.linalg.norm(vec2)
        
        if norm1 == 0 or norm2 == 0:
            return 0.0
        
        return dot_product / (norm1 * norm2)
    
    def get_all_documents(self, collection_name: Optional[str] = None) -> List[Dict]:
        """Lấy tất cả documents trong collection"""
        if collection_name is None:
            collection_name = self.collection_name
        
        cursor = self.connection.cursor(dictionary=True)
        
        cursor.execute(
            """
            SELECT vd.id, vd.content, vd.metadata, vd.created_at
            FROM vector_documents vd
            JOIN vector_collections vc ON vd.collection_id = vc.id
            WHERE vc.name = %s
            """,
            (collection_name,)
        )
        
        results = cursor.fetchall()
        cursor.close()
        
        return [{
            'id': row['id'],
            'content': row['content'],
            'metadata': json.loads(row['metadata']) if row['metadata'] else {},
            'created_at': str(row['created_at'])
        } for row in results]
    
    def collection_exists(self, collection_name: str) -> bool:
        """Kiểm tra collection có tồn tại không"""
        cursor = self.connection.cursor()
        cursor.execute(
            "SELECT COUNT(*) FROM vector_collections WHERE name = %s",
            (collection_name,)
        )
        result = cursor.fetchone()
        cursor.close()
        return result[0] > 0
    
    def count_documents(self, collection_name: Optional[str] = None) -> int:
        """Đếm số documents trong collection"""
        if collection_name is None:
            collection_name = self.collection_name
        
        cursor = self.connection.cursor()
        cursor.execute(
            """
            SELECT COUNT(*) FROM vector_documents vd
            JOIN vector_collections vc ON vd.collection_id = vc.id
            WHERE vc.name = %s
            """,
            (collection_name,)
        )
        result = cursor.fetchone()
        cursor.close()
        return result[0] if result else 0
    
    def close(self):
        """Đóng kết nối database"""
        if self.connection and self.connection.is_connected():
            self.connection.close()
            print("Đã đóng kết nối MySQL")
    
    def __del__(self):
        self.close()
