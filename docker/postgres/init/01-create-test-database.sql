-- Runs once, when the local PostgreSQL volume is first created.
-- Tests use this database (wiped on every run), never the "blood" development database.
CREATE DATABASE blood_test OWNER blood;
