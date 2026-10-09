-- Inspected live schema: books.user_id and books.book_image already exist.
-- Existing books all reference valid users. No columns or tables are added.
ALTER TABLE books ADD CONSTRAINT books_user_fk FOREIGN KEY (user_id) REFERENCES users(id);
