DROP TABLE IF EXISTS public.named_cell;

CREATE TABLE public.named_cell (
    id bigint NOT NULL,
    cell_name text,
    location_id bigint,
    interior integer,
    dest_door_cell_id bigint,
    dest_door_exterior bigint,
    door_id bigint NOT NULL,
    vanilla_cell boolean,
    statics_list text
)
WITH (autovacuum_enabled='on', toast.autovacuum_enabled='on');


ALTER TABLE public.named_cell OWNER TO dwemer;

--
-- Name: COLUMN named_cell.id; Type: COMMENT; Schema: public; Owner: dwemer
--

COMMENT ON COLUMN public.named_cell.id IS 'cell formid';


--
-- Name: COLUMN named_cell.location_id; Type: COMMENT; Schema: public; Owner: dwemer
--

COMMENT ON COLUMN public.named_cell.location_id IS 'should be ocation formid';


--
-- Name: COLUMN named_cell.dest_door_exterior; Type: COMMENT; Schema: public; Owner: dwemer
--

COMMENT ON COLUMN public.named_cell.dest_door_exterior IS '-1 is Unknown, -2 in-cell door, 1 points to exterior, 0 points to interior';


--
-- Name: named_cell cell_id_door_id; Type: CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.named_cell
    ADD CONSTRAINT cell_id_door_id PRIMARY KEY (id, door_id);