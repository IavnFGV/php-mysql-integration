use naukroom;


create table deals
(
    id             int auto_increment
        primary key,
    deal_id        int                                not null,
    person_id      int                                not null,
    title          text                               not null,
    stage_id       int                                not null,
    pipeline_id    int                                not null,
    correlation_id char(36)                           not null,
    meta_id        char(36)                           not null,
    value          int                                null,
    label_ids      text                               null,
    inserted_date  datetime default CURRENT_TIMESTAMP not null
);


create table loging
(
    id             int auto_increment
        primary key,
    date           timestamp default CURRENT_TIMESTAMP not null,
    correlation_id char(36),
    meta_id        char(36),
    state          text,
    requestData    text,
    identifier     text,
    description    text,
    request        text,
    ip             text,
    data           text                                not null
)
    engine = MyISAM
    collate = utf8mb4_unicode_ci;

create index date
    on loging (date);
