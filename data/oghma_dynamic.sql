CREATE TABLE IF NOT EXISTS public.oghma_dynamic (
    id integer NOT NULL,
    id_quest character varying(1024),
    stage integer,
    topic character varying,
    topic_desc text,
    knowledge_class text,
    topic_desc_basic text,
    knowledge_class_basic text,
    tags text,
    category text
);

ALTER TABLE public.oghma_dynamic OWNER TO dwemer;

CREATE SEQUENCE IF NOT EXISTS public.oghma_dynamic_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE public.oghma_dynamic_id_seq OWNER TO dwemer;

ALTER SEQUENCE public.oghma_dynamic_id_seq OWNED BY public.oghma_dynamic.id;

ALTER TABLE ONLY public.oghma_dynamic ALTER COLUMN id SET DEFAULT nextval('public.oghma_dynamic_id_seq'::regclass);

ALTER TABLE ONLY public.oghma_dynamic
    ADD CONSTRAINT oghma_dynamic_pkey PRIMARY KEY (id);
