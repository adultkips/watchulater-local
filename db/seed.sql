-- Seed default settings row
INSERT INTO settings (id, onboarded) VALUES (1, 0)
ON CONFLICT(id) DO NOTHING;
