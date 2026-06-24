from pathlib import Path
import os

DATA_DIR = Path("data")
DATA_DIR.mkdir(exist_ok=True)

os.environ["GEMINI_API_KEY"] = "AIzaSyB6pv-xINAvxXhCpu2pfKIg16EAJ8Z2AmA"

CHUNK_SIZE = 1000
CHUNK_OVERLAP = 100

GEMINI_EMBEDDING_MODEL = "models/text-embedding-004"

# MySQL Configuration
MYSQL_CONFIG = {
    "host": "localhost",
    "port": 3306,
    "database": "autoscore",
    "user": "root",
    "password": "123456"
}