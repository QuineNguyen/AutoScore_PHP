# Autoscore System

Autoscore System is an AI-powered automatic grading platform for essay-style and handwritten student submissions.

The project combines a PHP web client, an OCR microservice, and optional AI/vector services to process uploaded text or images, extract content, and generate grading feedback.

## Main Components

- `php-client`: Web interface for creating questions, uploading reference documents, submitting student work, and viewing grading history/results.
- `ocr-server`: FastAPI-based OCR service that extracts text from images/PDF files.
- `ai-vector-server`: Vector ingestion and retrieval service for document-based grading support (RAG).
- `ai-autograding-gemini`: Experimental notebooks/scripts for OCR and grading-related research.

## What It Does

- Accepts student answers as plain text or image uploads.
- Runs OCR to convert handwritten/image content into text.
- Supports rubric/model-answer-based grading workflows.
- Stores submissions and grading output for later review.
- Enables document upload and synchronization for retrieval-augmented grading scenarios.
