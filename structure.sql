CREATE TABLE IF NOT EXISTS storage (
  id CHAR(100),
  data TEXT NOT NULL,
  created_at timestamp NULL DEFAULT NULL,
  updated_at timestamp NULL DEFAULT NULL,

  PRIMARY KEY (id)
);
