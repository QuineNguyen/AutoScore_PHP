import os
from pathlib import Path
from langchain.document_loaders import PyPDFLoader, TextLoader, Docx2txtLoader, UnstructuredWordDocumentLoader
from langchain_chroma import Chroma
from utils.embeddings import get_embeddings
from utils.text_splitter import get_text_splitter
from config import DATA_DIR

embeddings = get_embeddings()
text_splitter = get_text_splitter()

def load_document(file_path: str):
    file_path = str(file_path)
    if file_path.endswith(".pdf"):
        loader = PyPDFLoader(file_path)
    elif file_path.endswith(".txt"):
        loader = TextLoader(file_path, encoding="utf-8")
    elif file_path.endswith(".docx"):
        loader = Docx2txtLoader(file_path)
    else:
        raise ValueError(f"Không hỗ trợ định dạng: {file_path}")
    return loader.load()

def ingest_file(file_path: str, collection_name: str = "default_collection"):
    docs = load_document(file_path)
    chunks = text_splitter.split_documents(docs)
    
    for chunk in chunks:
        chunk.metadata["source"] = os.path.basename(file_path)
    
    vectorstore = Chroma(
        collection_name=collection_name,
        embedding_function=embeddings,
        persist_directory="./chroma_db"
    )
    vectorstore.add_documents(chunks)
    vectorstore.persist()
    print(f"Đã ingest {len(chunks)} chunks từ {file_path}")