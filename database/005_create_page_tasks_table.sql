-- ================================================================
-- AP5: Page-Task Relations
-- Links tasks to pages via a many-to-many junction table.
-- ================================================================

CREATE TABLE IF NOT EXISTS page_tasks (
    page_id     INT          NOT NULL,
    task_id     INT          NOT NULL,
    sort_order  INT          NOT NULL DEFAULT 0,
    created_by  INT          NOT NULL,
    created_at  DATETIME     NOT NULL,

    PRIMARY KEY (page_id, task_id),

    INDEX idx_page_sort (page_id, sort_order),
    INDEX idx_task (task_id),

    CONSTRAINT fk_pt_page    FOREIGN KEY (page_id)    REFERENCES pages(id) ON DELETE CASCADE,
    CONSTRAINT fk_pt_task    FOREIGN KEY (task_id)    REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_pt_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
