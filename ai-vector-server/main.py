from fastapi import FastAPI, Request, HTTPException
from pydantic import BaseModel
from typing import List, Dict, Any, Optional
import uuid
from datetime import datetime
from utils.embeddings import get_embeddings
from utils.text_splitter import get_text_splitter, split_text_clean
from utils.mysql_vectorstore import MySQLVectorStore
from langchain.schema import Document

app = FastAPI(title="AutoScore AI Server - Gemini + MySQL Vector")

embeddings = get_embeddings()
text_splitter = get_text_splitter()

# ========================================
# Models cho Tài liệu tham khảo
# ========================================
class ReferenceDocument(BaseModel):
    filename: str
    content: str

class SyncDocumentsPayload(BaseModel):
    """Payload để sync tài liệu tham khảo vào Vector DB"""
    action: str  # "sync_documents"
    documents: List[ReferenceDocument]
    synced_at: str
    source: str = "autoscore_system"

# ========================================
# Models cho Câu hỏi
# ========================================
class CreateQuestionPayload(BaseModel):
    """Payload để tạo câu hỏi (không bao gồm tài liệu)"""
    action: str  # "create_question"
    question_id: str
    question_text: str
    model_answer: Optional[str] = ""
    grading_strategy: str
    max_score: int
    synced_at: str
    source: str = "autoscore_system"

# ========================================
# Models cho RAG Search
# ========================================
class RAGSearchPayload(BaseModel):
    """Payload để tìm kiếm RAG"""
    question_text: str
    top_k: int = 5

class RAGSearchResponse(BaseModel):
    """Response từ RAG search"""
    status: str
    question_text: str
    retrieved_chunks: List[Dict[str, Any]]
    generated_answer: str
    chunks_count: int
    processed_at: str

# ========================================
# Legacy payload (backward compatibility)
# ========================================
class SyncQuestionPayload(BaseModel):
    action: str
    question_id: str
    question_text: str
    model_answer: str
    grading_strategy: str
    max_score: int
    reference_documents: List[ReferenceDocument]
    synced_at: str
    source: str = "autoscore_system"


# ========================================
# API Endpoints
# ========================================

@app.post("/webhook/sync-documents")
async def sync_documents(payload: SyncDocumentsPayload):
    """
    Endpoint riêng để sync tài liệu tham khảo vào Vector DB.
    Tài liệu được lưu vào collection chung 'reference_documents'.
    """
    import time
    start_time = time.time()
    
    if payload.action != "sync_documents":
        raise HTTPException(400, detail="Action không hợp lệ. Yêu cầu: sync_documents")

    collection_name = "reference_documents"

    documents = []
    for doc in payload.documents:
        chunks = split_text_clean(doc.content, text_splitter)
        for i, chunk in enumerate(chunks):
            documents.append(Document(
                page_content=chunk,
                metadata={
                    "source": doc.filename,
                    "chunk_index": i,
                    "synced_at": payload.synced_at,
                    "type": "reference"
                }
            ))

    if not documents:
        return {
            "status": "success",
            "message": "Không có tài liệu nào để sync",
            "chunks_ingested": 0,
            "processed_at": datetime.now().isoformat()
        }

    vectorstore = MySQLVectorStore(
        embedding_function=embeddings,
        collection_name=collection_name
    )

    # Xóa dữ liệu cũ và thêm mới
    vectorstore.truncate_all_data()
    vectorstore.add_documents(documents, collection_name)
    
    total_time = time.time() - start_time

    return {
        "status": "success",
        "collection": collection_name,
        "documents_count": len(payload.documents),
        "chunks_ingested": len(documents),
        "timing": f"{total_time:.2f}s",
        "processed_at": datetime.now().isoformat()
    }


@app.post("/webhook/create-question")
async def create_question(payload: CreateQuestionPayload):
    """
    Endpoint riêng để tạo câu hỏi.
    Chỉ lưu thông tin câu hỏi, không xử lý tài liệu.
    """
    if payload.action != "create_question":
        raise HTTPException(400, detail="Action không hợp lệ. Yêu cầu: create_question")

    # Có thể thêm logic lưu câu hỏi vào DB riêng nếu cần
    # Hiện tại chỉ return success để PHP biết đã nhận được

    return {
        "status": "success",
        "question_id": payload.question_id,
        "grading_strategy": payload.grading_strategy,
        "message": "Câu hỏi đã được tạo thành công",
        "processed_at": datetime.now().isoformat()
    }


@app.post("/webhook/rag-search")
async def rag_search(payload: RAGSearchPayload):
    """
    Endpoint để tìm kiếm RAG - embedding câu hỏi và tìm các chunks tương đồng.
    
    Quy trình:
    1. Embedding câu hỏi thành vector
    2. Tìm top_k chunks có embedding vector tương đồng nhất trong collection 'reference_documents'
    3. Ghép các content thành answer
    4. Trả về answer để PHP lưu vào model_answer
    """
    import time
    start_time = time.time()
    
    collection_name = "reference_documents"
    
    vectorstore = MySQLVectorStore(
        embedding_function=embeddings,
        collection_name=collection_name
    )
    
    similar_chunks = vectorstore.similarity_search(
        query=payload.question_text,
        k=payload.top_k,
        collection_name=collection_name
    )
    
    if not similar_chunks:
        return {
            "status": "success",
            "question_text": payload.question_text,
            "retrieved_chunks": [],
            "generated_answer": "",
            "chunks_count": 0,
            "message": "Không tìm thấy tài liệu tham khảo phù hợp. Vui lòng sync tài liệu trước.",
            "timing": f"{time.time() - start_time:.2f}s",
            "processed_at": datetime.now().isoformat()
        }
    
    answer_parts = []
    for chunk in similar_chunks:
        content = chunk.get('content', '')
        if content:
            answer_parts.append(content.strip())

    generated_answer = "\n---\n".join(answer_parts)

    print("Generated Answer:", generated_answer)
    
    retrieved_chunks_info = []
    for chunk in similar_chunks:
        retrieved_chunks_info.append({
            "content": chunk.get('content', ''),
            "source": chunk.get('metadata', {}).get('source', 'Unknown'),
            "similarity": round(chunk.get('similarity', 0), 4),
            "chunk_index": chunk.get('metadata', {}).get('chunk_index', 0)
        })
    
    total_time = time.time() - start_time
    
    return {
        "status": "success",
        "question_text": payload.question_text,
        "retrieved_chunks": retrieved_chunks_info,
        "generated_answer": generated_answer,
        "chunks_count": len(similar_chunks),
        "timing": f"{total_time:.2f}s",
        "processed_at": datetime.now().isoformat()
    }


@app.post("/webhook/sync-question")
async def sync_question(payload: SyncQuestionPayload):
    """
    Legacy endpoint - Giữ lại để backward compatibility.
    Sync cả câu hỏi và tài liệu cùng lúc.
    """
    if payload.action != "create_question":
        raise HTTPException(400, detail="Action không hợp lệ")

    question_id = payload.question_id
    collection_name = f"question_{question_id}"

    documents = []
    for doc in payload.reference_documents:
        chunks = split_text_clean(doc.content, text_splitter)
        for i, chunk in enumerate(chunks):
            documents.append(Document(
                page_content=chunk,
                metadata={
                    "source": doc.filename,
                    "question_id": question_id,
                    "question_text": payload.question_text,
                    "chunk_index": i,
                    "synced_at": payload.synced_at,
                    "type": "reference"
                }
            ))

    vectorstore = MySQLVectorStore(
        embedding_function=embeddings,
        collection_name=collection_name
    )

    vectorstore.truncate_all_data()
    vectorstore.add_documents(documents, collection_name)

    return {
        "status": "success",
        "question_id": question_id,
        "collection": collection_name,
        "chunks_ingested": len(documents),
        "processed_at": datetime.now().isoformat()
    }


if __name__ == "__main__":
    import uvicorn

    uvicorn.run("main:app", host="127.0.0.1", port=8000, reload=True)