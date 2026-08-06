-- Tags each correction with which verification engine produced the wrong
-- read. Now that Claude vision is the primary path (Google Vision OCR +
-- regex only runs as a fallback when Claude's API is unavailable), the two
-- engines fail in different, unrelated ways — an OCR glyph-misread trap
-- ("₹5 misread as 35") is not a mistake a vision-reasoning model is prone
-- to, so few-shotting Claude with Google-Vision-era mistakes is noise, not
-- signal. This lets recentPoScreenshotCorrections() draw examples only from
-- the engine actually being prompted.
ALTER TABLE tp_po_screenshot_ocr_corrections
  ADD COLUMN engine VARCHAR(20) NOT NULL DEFAULT 'google_vision' AFTER field,
  ADD KEY idx_tppsoc_engine (engine);
