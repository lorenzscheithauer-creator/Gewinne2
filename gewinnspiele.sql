CREATE TABLE gewinnspiele (
    id INT AUTO_INCREMENT PRIMARY KEY,
    link_zur_webseite VARCHAR(500) NOT NULL,
    beschreibung TEXT
);

ALTER TABLE gewinnspiele
ADD UNIQUE KEY unique_link (link_zur_webseite);
