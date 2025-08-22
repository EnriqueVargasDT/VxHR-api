CREATE TABLE [dbo].[reactions_catalog] (
    [id]    INT            IDENTITY (1, 1) NOT NULL,
    [code]  VARCHAR (50)   DEFAULT (NULL) NULL,
    [icon]  NVARCHAR (100) DEFAULT (NULL) NULL,
    [label] NVARCHAR (50)  DEFAULT (NULL) NULL,
    PRIMARY KEY CLUSTERED ([id] ASC),
    CONSTRAINT [UQ__reactions_catalog_code] UNIQUE NONCLUSTERED ([code] ASC)
);

CREATE TABLE [dbo].[post_types] (
    [id]          INT            IDENTITY (1, 1) NOT NULL,
    [code]        VARCHAR (50)   DEFAULT (NULL) NULL,
    [name]        NVARCHAR (100) DEFAULT (NULL) NULL,
    [description] NVARCHAR (255) DEFAULT (NULL) NULL,
    PRIMARY KEY CLUSTERED ([id] ASC),
    CONSTRAINT [UQ__post_types_code] UNIQUE NONCLUSTERED ([code] ASC)
);

CREATE TABLE [dbo].[posts] (
    [id]           INT            IDENTITY (1, 1) NOT NULL,
    [author_id]    INT            NOT NULL,
    [post_type_id] INT            NOT NULL,
    [title]        NVARCHAR (200) DEFAULT (NULL) NULL,
    [content]      NVARCHAR (MAX) DEFAULT (NULL) NULL,
    [created_at]   DATETIME       DEFAULT (getdate()) NULL,
    [published_at] DATETIME       CONSTRAINT [DEFAULT_posts_published_at] DEFAULT (getdate()) NULL,
    [updated_at]   DATETIME       DEFAULT (NULL) NULL,
    [deleted]      BIT            DEFAULT ((0)) NULL,
    PRIMARY KEY CLUSTERED ([id] ASC),
    CONSTRAINT [fk_posts_author] FOREIGN KEY ([author_id]) REFERENCES [user].[users] ([pk_user_id]),
    CONSTRAINT [fk_posts_type] FOREIGN KEY ([post_type_id]) REFERENCES [dbo].[post_types] ([id])
);

CREATE TABLE [dbo].[comments] (
    [id]                INT            IDENTITY (1, 1) NOT NULL,
    [post_id]           INT            NOT NULL,
    [parent_comment_id] INT            DEFAULT (NULL) NULL,
    [user_id]           INT            NOT NULL,
    [content]           NVARCHAR (MAX) DEFAULT (NULL) NULL,
    [created_at]        DATETIME       DEFAULT (getdate()) NULL,
    [deleted]           BIT            DEFAULT ((0)) NULL,
    PRIMARY KEY CLUSTERED ([id] ASC),
    CONSTRAINT [fk_comments_parent] FOREIGN KEY ([parent_comment_id]) REFERENCES [dbo].[comments] ([id]),
    CONSTRAINT [fk_comments_post] FOREIGN KEY ([post_id]) REFERENCES [dbo].[posts] ([id]),
    CONSTRAINT [fk_comments_user] FOREIGN KEY ([user_id]) REFERENCES [user].[users] ([pk_user_id])
);

CREATE TABLE [dbo].[comment_images] (
    [id]         INT            IDENTITY (1, 1) NOT NULL,
    [comment_id] INT            NOT NULL,
    [image_url]  NVARCHAR (500) DEFAULT (NULL) NULL,
    PRIMARY KEY CLUSTERED ([id] ASC),
    CONSTRAINT [fk_comment_images_comment] FOREIGN KEY ([comment_id]) REFERENCES [dbo].[comments] ([id])
);

CREATE TABLE [dbo].[comment_links] (
    [id]                     INT             IDENTITY (1, 1) NOT NULL,
    [comment_id]             INT             NOT NULL,
    [external_url]           NVARCHAR (1000) DEFAULT (NULL) NULL,
    [internal_redirect_path] NVARCHAR (500)  DEFAULT (NULL) NULL,
    PRIMARY KEY CLUSTERED ([id] ASC),
    CONSTRAINT [fk_comment_links_comment] FOREIGN KEY ([comment_id]) REFERENCES [dbo].[comments] ([id])
);

CREATE TABLE [dbo].[comment_mentions] (
    [comment_id]        INT NOT NULL,
    [mentioned_user_id] INT NOT NULL,
    PRIMARY KEY CLUSTERED ([comment_id] ASC, [mentioned_user_id] ASC),
    CONSTRAINT [fk_comment_mentions_comment] FOREIGN KEY ([comment_id]) REFERENCES [dbo].[comments] ([id]),
    CONSTRAINT [fk_comment_mentions_user] FOREIGN KEY ([mentioned_user_id]) REFERENCES [user].[users] ([pk_user_id])
);

CREATE TABLE [dbo].[comment_reactions] (
    [comment_id]  INT      IDENTITY (1, 1) NOT NULL,
    [user_id]     INT      NOT NULL,
    [reaction_id] INT      NOT NULL,
    [created_at]  DATETIME DEFAULT (getdate()) NULL,
    PRIMARY KEY CLUSTERED ([comment_id] ASC, [user_id] ASC),
    CONSTRAINT [fk_comment_reactions_comment] FOREIGN KEY ([comment_id]) REFERENCES [dbo].[comments] ([id]),
    CONSTRAINT [fk_comment_reactions_reaction] FOREIGN KEY ([reaction_id]) REFERENCES [dbo].[reactions_catalog] ([id]),
    CONSTRAINT [fk_comment_reactions_user] FOREIGN KEY ([user_id]) REFERENCES [user].[users] ([pk_user_id])
);

CREATE TABLE [dbo].[post_images] (
    [id]        INT            IDENTITY (1, 1) NOT NULL,
    [post_id]   INT            NOT NULL,
    [image_url] NVARCHAR (500) DEFAULT (NULL) NULL,
    PRIMARY KEY CLUSTERED ([id] ASC),
    CONSTRAINT [fk_post_images_post] FOREIGN KEY ([post_id]) REFERENCES [dbo].[posts] ([id])
);

CREATE TABLE [dbo].[post_links] (
    [id]          INT             IDENTITY (1, 1) NOT NULL,
    [post_id]     INT             NOT NULL,
    [src]         NVARCHAR (1000) NOT NULL,
    [title]       NVARCHAR (500)  NOT NULL,
    [description] NVARCHAR (500)  NULL,
    PRIMARY KEY CLUSTERED ([id] ASC),
    CONSTRAINT [fk_post_links_post] FOREIGN KEY ([post_id]) REFERENCES [dbo].[posts] ([id])
);

CREATE TABLE [dbo].[post_reactions] (
    [post_id]     INT      NOT NULL,
    [user_id]     INT      NOT NULL,
    [reaction_id] INT      NOT NULL,
    [created_at]  DATETIME DEFAULT (getdate()) NULL,
    PRIMARY KEY CLUSTERED ([post_id] ASC, [user_id] ASC),
    CONSTRAINT [fk_post_reactions_post] FOREIGN KEY ([post_id]) REFERENCES [dbo].[posts] ([id]),
    CONSTRAINT [fk_post_reactions_reaction] FOREIGN KEY ([reaction_id]) REFERENCES [dbo].[reactions_catalog] ([id]),
    CONSTRAINT [fk_post_reactions_user] FOREIGN KEY ([user_id]) REFERENCES [user].[users] ([pk_user_id])
);

CREATE TABLE [dbo].[post_saves] (
    [post_id]  INT      NOT NULL,
    [user_id]  INT      NOT NULL,
    [saved_at] DATETIME DEFAULT (getdate()) NULL,
    PRIMARY KEY CLUSTERED ([post_id] ASC, [user_id] ASC),
    CONSTRAINT [fk_post_saves_post] FOREIGN KEY ([post_id]) REFERENCES [dbo].[posts] ([id]),
    CONSTRAINT [fk_post_saves_user] FOREIGN KEY ([user_id]) REFERENCES [user].[users] ([pk_user_id])
);

CREATE TABLE [dbo].[post_targets] (
    [post_id]        INT NOT NULL,
    [target_user_id] INT NOT NULL,
    PRIMARY KEY CLUSTERED ([post_id] ASC),
    CONSTRAINT [fk_post_targets_post] FOREIGN KEY ([post_id]) REFERENCES [dbo].[posts] ([id]),
    CONSTRAINT [fk_post_targets_user] FOREIGN KEY ([target_user_id]) REFERENCES [user].[users] ([pk_user_id])
);

CREATE TABLE [dbo].[post_user_emails] (
    [id]          INT          NOT NULL,
    [code]        VARCHAR (50) DEFAULT (NULL) NULL,
    [name]        VARCHAR (50) DEFAULT (NULL) NULL,
    [description] VARCHAR (50) DEFAULT (NULL) NULL,
    PRIMARY KEY CLUSTERED ([id] ASC)
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
