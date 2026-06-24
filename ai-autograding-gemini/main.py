# main.py - Server chấm điểm tự động bằng Gemini 1.5 Pro (chạy riêng biệt)
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import google.generativeai as genai
import re
import json
import logging

GEMINI_API_KEY = "AIzaSyB6pv-xINAvxXhCpu2pfKIg16EAJ8Z2AmA"

genai.configure(api_key=GEMINI_API_KEY)

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(
    title="Auto Grading Service",
    description="Server chuyên dụng chấm điểm tự động bằng Gemini 1.5 Pro",
    version="1.0"
)

class GradingRequest(BaseModel):
    requirement: str
    student_work: str
    model_answer: str

class GradingResponse(BaseModel):
    score: float
    feedback: str

@app.get("/grade")
async def root():
    return {"status": "ok", "message": "Auto Grading Server đang chạy ngon lành!"}

@app.post("/grade", response_model=GradingResponse)
async def grade_submission(payload: GradingRequest):
    prompt = f"""
Hãy đóng vai trò là một chuyên gia chấm thi khách quan và nghiêm khắc. Nhiệm vụ của bạn là đánh giá câu trả lời của học sinh dựa trên câu hỏi và đáp án chuẩn được cung cấp. 
Dưới đây là dữ liệu đầu vào: 
--- [CÂU HỎI]: 
{payload.requirement} 
[ĐÁP ÁN CHUẨN]: 
{payload.model_answer} 
[CÂU TRẢ LỜI CỦA HỌC SINH]: 
{payload.student_work} 
--- Yêu cầu xử lý: 
1. So sánh kỹ lưỡng ý nghĩa, từ khóa và logic của câu trả lời học sinh so với đáp án chuẩn. 
2. Chấm điểm trên thang điểm từ 0 đến 10 (có thể dùng số thập phân, ví dụ: 8.5). 
- 0 điểm: Sai hoàn toàn hoặc không trả lời. 
- 10 điểm: Chính xác hoàn toàn, đầy đủ ý như đáp án chuẩn. 
3. Giải thích ngắn gọn lý do tại sao cho số điểm đó (chỉ ra lỗi sai hoặc phần thiếu nếu có). 
QUAN TRỌNG: 
- Bạn chỉ được phép trả về kết quả dưới dạng JSON thuần túy. 
- Không được thêm bất kỳ văn bản, lời chào, hay định dạng markdown (```json) nào khác vào đầu hoặc cuối. 
- Cấu trúc JSON bắt buộc như sau: {{ "score": "<số_điểm>", "feedback": "<lời_giải_thích>" }}

"""

    text = ""
    
    try:
        model = genai.GenerativeModel(
            model_name="gemini-2.5-flash",
            generation_config=genai.GenerationConfig(
                temperature=0.3,
                max_output_tokens=8192,
                top_p=0.95,
                top_k=40
            )
        )

        response = model.generate_content(prompt)
        text = response.text.strip()

        logger.info(f"Gemini raw response: {text[:200]}...")

        json_match = re.search(r'\{[^}]*"score"\s*:\s*\d+\.?\d*[^}]*\}', text, re.DOTALL)
        if not json_match:
            json_match = re.search(r'\{.*\}', text, re.DOTALL)

        if json_match:
            data = json.loads(json_match.group())
            score = float(data.get("score", 0))
            feedback = data.get("feedback", "").strip()

            score = min(max(round(score, 2), 0.0), 10.0)

            return GradingResponse(score=score, feedback=feedback)

    except Exception as e:
        logger.error(f"Lỗi khi gọi Gemini: {e}")

    score_match = re.search(r'(\d+\.?\d*)', text) if 'text' in locals() else None
    score = float(score_match.group(1)) if score_match else 0.0
    score = min(max(round(score, 2), 0.0), 10.0)

    feedback = re.sub(r'```json|```', '', text or "").strip()
    if len(feedback) > 500:
        feedback = feedback[:500] + "..."

    return GradingResponse(
        score=score,
        feedback=feedback or "Hệ thống tạm thời không thể đưa ra phản hồi chi tiết."
    )

if __name__ == "__main__":
    import uvicorn

    uvicorn.run("main:app", host="127.0.0.1", port=8002, reload=True)