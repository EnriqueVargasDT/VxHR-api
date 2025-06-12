CREATE TABLE post_types (
    id INT PRIMARY KEY IDENTITY,
    code VARCHAR(50) UNIQUE,
    name NVARCHAR(100),
    description NVARCHAR(255)
);

CREATE TABLE posts (
    id INT PRIMARY KEY IDENTITY,
    author_id INT NOT NULL,
    post_type_id INT NOT NULL,
    title NVARCHAR(200),
    content NVARCHAR(MAX),
    created_at DATETIME DEFAULT GETDATE(),
    updated_at DATETIME NULL,
    deleted BIT DEFAULT 0,

    CONSTRAINT fk_posts_author FOREIGN KEY (author_id) REFERENCES [user].users(pk_user_id),
    CONSTRAINT fk_posts_type FOREIGN KEY (post_type_id) REFERENCES post_types(id)
    
);

CREATE TABLE post_targets (
    post_id INT PRIMARY KEY,
    target_user_id INT NOT NULL,

    CONSTRAINT fk_post_targets_post FOREIGN KEY (post_id) REFERENCES posts(id),
    CONSTRAINT fk_post_targets_user FOREIGN KEY (target_user_id) REFERENCES [user].users(pk_user_id)
);

CREATE TABLE post_images (
    id INT PRIMARY KEY IDENTITY,
    post_id INT NOT NULL,
    image_url NVARCHAR(500),

    CONSTRAINT fk_post_images_post FOREIGN KEY (post_id) REFERENCES posts(id)
);

CREATE TABLE post_links (
    id INT PRIMARY KEY IDENTITY,
    post_id INT NOT NULL,
    external_url NVARCHAR(1000),
    internal_redirect_path NVARCHAR(500),

    CONSTRAINT fk_post_links_post FOREIGN KEY (post_id) REFERENCES posts(id)
);

CREATE TABLE reactions_catalog (
    id INT PRIMARY KEY IDENTITY,
    code VARCHAR(50) UNIQUE,
    icon NVARCHAR(100),
    label NVARCHAR(50)
);

CREATE TABLE post_reactions (
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    reaction_id INT NOT NULL,
    created_at DATETIME DEFAULT GETDATE(),

    PRIMARY KEY (post_id, user_id),
    CONSTRAINT fk_post_reactions_post FOREIGN KEY (post_id) REFERENCES posts(id),
    CONSTRAINT fk_post_reactions_user FOREIGN KEY (user_id) REFERENCES [user].users(pk_user_id),
    CONSTRAINT fk_post_reactions_reaction FOREIGN KEY (reaction_id) REFERENCES reactions_catalog(id)
);

CREATE TABLE comments (
    id INT PRIMARY KEY IDENTITY,
    post_id INT NOT NULL,
    parent_comment_id INT NULL,
    user_id INT NOT NULL,
    content NVARCHAR(MAX),
    created_at DATETIME DEFAULT GETDATE(),
    deleted BIT DEFAULT 0,

    CONSTRAINT fk_comments_post FOREIGN KEY (post_id) REFERENCES posts(id),
    CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES [user].users(pk_user_id),
    CONSTRAINT fk_comments_parent FOREIGN KEY (parent_comment_id) REFERENCES comments(id)
);

CREATE TABLE comment_images (
    id INT PRIMARY KEY IDENTITY,
    comment_id INT NOT NULL,
    image_url NVARCHAR(500),

    CONSTRAINT fk_comment_images_comment FOREIGN KEY (comment_id) REFERENCES comments(id)
);

CREATE TABLE comment_links (
    id INT PRIMARY KEY IDENTITY,
    comment_id INT NOT NULL,
    external_url NVARCHAR(1000),
    internal_redirect_path NVARCHAR(500),

    CONSTRAINT fk_comment_links_comment FOREIGN KEY (comment_id) REFERENCES comments(id)
);

CREATE TABLE comment_mentions (
    comment_id INT NOT NULL,
    mentioned_user_id INT NOT NULL,

    PRIMARY KEY (comment_id, mentioned_user_id),
    CONSTRAINT fk_comment_mentions_comment FOREIGN KEY (comment_id) REFERENCES comments(id),
    CONSTRAINT fk_comment_mentions_user FOREIGN KEY (mentioned_user_id) REFERENCES [user].users(pk_user_id)
);

CREATE TABLE comment_reactions (
    comment_id INT NOT NULL,
    user_id INT NOT NULL,
    reaction_id INT NOT NULL,
    created_at DATETIME DEFAULT GETDATE(),

    PRIMARY KEY (comment_id, user_id),
    CONSTRAINT fk_comment_reactions_comment FOREIGN KEY (comment_id) REFERENCES comments(id),
    CONSTRAINT fk_comment_reactions_user FOREIGN KEY (user_id) REFERENCES [user].users(pk_user_id),
    CONSTRAINT fk_comment_reactions_reaction FOREIGN KEY (reaction_id) REFERENCES reactions_catalog(id)
);

CREATE TABLE post_saves (
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    saved_at DATETIME DEFAULT GETDATE(),

    PRIMARY KEY (post_id, user_id),
    CONSTRAINT fk_post_saves_post FOREIGN KEY (post_id) REFERENCES posts(id),
    CONSTRAINT fk_post_saves_user FOREIGN KEY (user_id) REFERENCES [user].users(pk_user_id)
);

-- Insertar tipos de publicación
INSERT INTO post_types (code, name, description) VALUES
('birthday', 'Cumpleaños', 'Publicación de cumpleaños'),
('anniversary', 'Aniversario', 'Celebración de aniversario'),
('announcement', 'Comunicado', 'Comunicado importante'),
('new_hire', 'Nuevos ingresos', 'Bienvenida a nuevos empleados'),
('general', 'Post general', 'Cualquier otro tipo de contenido');

-- Insertar catálogo de reacciones
INSERT INTO reactions_catalog (code, icon, label) VALUES
('like', '👍', 'Me gusta'),
('love', '❤️', 'Me encanta'),
('applause', '👏', 'Aplausos'),
('birthday-cake', '🎂', 'Feliz cumpleaños'),
('smile', '😊', 'Me alegra'),
('trophy', '🏆', 'Logro'),
('idea', '💡', 'Buena idea'),
('appreciation', '🙌', 'Agradecimiento'),
('sympathy', '🤝', 'Apoyo'),
('star', '⭐', 'Estrella');
