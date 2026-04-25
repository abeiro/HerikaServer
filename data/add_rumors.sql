CREATE TABLE public.rumors (
    id integer NOT NULL,
    gamets bigint,
    ts bigint,
    hold text,
    content text,
    type text,
    rumor_length_days integer
);


ALTER TABLE public.rumors OWNER TO dwemer;

--
-- Name: TABLE rumors; Type: COMMENT; Schema: public; Owner: dwemer
--

COMMENT ON TABLE public.rumors IS 'rumors and news per hold';


--
-- Name: rumors_id_seq; Type: SEQUENCE; Schema: public; Owner: dwemer
--

CREATE SEQUENCE public.rumors_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.rumors_id_seq OWNER TO dwemer;

--
-- Name: rumors_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: dwemer
--

ALTER SEQUENCE public.rumors_id_seq OWNED BY public.rumors.id;


--
-- Name: rumors id; Type: DEFAULT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.rumors ALTER COLUMN id SET DEFAULT nextval('public.rumors_id_seq'::regclass);
