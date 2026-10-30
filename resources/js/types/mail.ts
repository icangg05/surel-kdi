export type Recipient = { email: string; name?: string | null };

export type MailboxSummary = { id: number; name: string; address: string };

export type MailShared = {
    mailboxes: MailboxSummary[];
    current: number | null;
    signature: string | null;
    counts: {
        inbox: number;
        spam: number;
        drafts: number;
        scheduled: number;
    } | null;
    quota: {
        used: number;
        limit: number;
        remaining: number;
        resets_at: string;
    };
    brevo_api: boolean;
    smtp_error: { message: string; at: string } | null;
    broadcast_hint: number;
    max_attachments_mb: number;
};

export type StatusValue = { value: string; label: string };

export type ListRow = {
    key: string;
    kind: 'thread' | 'draft' | 'outgoing';
    id: number;
    thread_id?: number | null;
    subject: string;
    snippet: string;
    participants: string;
    date: string | null;
    unread: boolean;
    starred: boolean;
    has_attachments: boolean;
    count: number;
    folder: string | null;
    broadcast: boolean;
    status: StatusValue | null;
    error?: string | null;
};

export type AttachmentItem = {
    id: number;
    filename: string;
    mime: string;
    size: number;
    available: boolean;
    error: string | null;
    url: string;
};

export type DeliveryItem = {
    email: string;
    name: string | null;
    status: string;
    label: string;
    opened_at: string | null;
    error: string | null;
};

export type MessageItem = {
    id: number;
    direction: 'in' | 'out';
    status: StatusValue;
    from_name: string | null;
    from_email: string;
    to: Recipient[];
    cc: Recipient[];
    bcc: Recipient[];
    reply_to: string | null;
    subject: string;
    snippet: string;
    date: string | null;
    is_read: boolean;
    body_url: string;
    has_remote_images: boolean;
    spam_score: number | null;
    error: string | null;
    author: string | null;
    contact: {
        id: number;
        name: string;
        position: string | null;
        organization: string | null;
        email: string;
    } | null;
    attachments: AttachmentItem[];
    deliveries: DeliveryItem[];
};

export type ThreadDetail = {
    id: number;
    subject: string;
    folder: string | null;
    is_starred: boolean;
    broadcast: { id: number; subject: string } | null;
    messages: MessageItem[];
};

export type Draft = {
    id: number;
    mailbox_id: number;
    thread_id: number | null;
    to: Recipient[];
    cc: Recipient[];
    bcc: Recipient[];
    subject: string;
    body_html: string;
    attachments: AttachmentItem[];
    is_reply: boolean;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    next_page_url: string | null;
    prev_page_url: string | null;
    links: { url: string | null; label: string; active: boolean }[];
};

export type GroupItem = {
    id: number;
    name: string;
    description?: string | null;
    color: string;
    contacts_count?: number;
    sendable_count?: number;
};
