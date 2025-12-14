CREATE TABLE public.named_cell (
    id integer NOT NULL,
    name text,
    location integer
);


ALTER TABLE public.named_cell OWNER TO dwemer;

--
-- Name: COLUMN named_cell.id; Type: COMMENT; Schema: public; Owner: dwemer
--

COMMENT ON COLUMN public.named_cell.id IS 'is formid';


--
-- Name: COLUMN named_cell.location; Type: COMMENT; Schema: public; Owner: dwemer
--

COMMENT ON COLUMN public.named_cell.location IS 'should be ocation formid';


--
-- Name: named_cell named_cell_pkey; Type: CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.named_cell
    ADD CONSTRAINT named_cell_pkey PRIMARY KEY (id);