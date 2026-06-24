from langchain_google_genai import GoogleGenerativeAIEmbeddings
from config import GEMINI_EMBEDDING_MODEL
import os

def get_embeddings():
    # Tự động lấy API key từ env hoặc config
    return GoogleGenerativeAIEmbeddings(
        model=GEMINI_EMBEDDING_MODEL,
        google_api_key=os.getenv("GEMINI_API_KEY"),
        task_type="retrieval_document",
    )