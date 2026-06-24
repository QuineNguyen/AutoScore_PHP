from langchain.text_splitter import RecursiveCharacterTextSplitter
import re

def clean_pdf_text(text: str) -> str:
    """
    Làm sạch text từ PDF:
    - Loại bỏ header/footer lặp lại
    - Chuẩn hóa khoảng trắng
    - Loại bỏ số trang
    """
    # Loại bỏ các dòng chỉ chứa số (số trang)
    text = re.sub(r'^\s*\d+\s*$', '', text, flags=re.MULTILINE)
    
    # Loại bỏ nhiều dòng trống liên tiếp thành 1 dòng trống
    text = re.sub(r'\n{3,}', '\n\n', text)
    
    # Loại bỏ khoảng trắng thừa đầu/cuối mỗi dòng
    lines = [line.strip() for line in text.split('\n')]
    text = '\n'.join(lines)
    
    # Loại bỏ header lặp lại (các dòng xuất hiện > 3 lần và ngắn < 100 ký tự)
    line_counts = {}
    for line in lines:
        if line and len(line) < 100:
            line_counts[line] = line_counts.get(line, 0) + 1
    
    repeated_headers = {line for line, count in line_counts.items() if count > 3}
    
    if repeated_headers:
        cleaned_lines = [line for line in lines if line not in repeated_headers]
        text = '\n'.join(cleaned_lines)
    
    return text.strip()

def get_text_splitter():
    return RecursiveCharacterTextSplitter(
        chunk_size=1000,
        chunk_overlap=200,
        length_function=len,
        separators=[
            "\n\n",      # Đoạn văn
            "\n",        # Xuống dòng
            "。",        # Dấu chấm tiếng Nhật/Trung
            ".",         # Dấu chấm câu
            " ",         # Khoảng trắng
            ""           # Fallback
        ]
    )

def split_text_clean(text: str, text_splitter=None) -> list:
    """
    Làm sạch và cắt text thành chunks
    """
    if text_splitter is None:
        text_splitter = get_text_splitter()
    
    # Làm sạch text trước khi cắt
    cleaned_text = clean_pdf_text(text)
    
    # Cắt thành chunks
    chunks = text_splitter.split_text(cleaned_text)
    
    # Loại bỏ chunks quá ngắn (< 50 ký tự)
    chunks = [chunk for chunk in chunks if len(chunk.strip()) >= 50]
    
    return chunks