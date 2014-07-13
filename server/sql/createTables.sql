CREATE TABLE msg_message (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  text TEXT,
  author VARCHAR(30),
  tags TEXT,
  date DATETIME
);
