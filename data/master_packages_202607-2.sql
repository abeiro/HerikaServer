

--
-- Data for Name: master_packages; Type: TABLE DATA; Schema: public; Owner: dwemer
--
ALTER TABLE ONLY public.master_packages
    ADD CONSTRAINT master_packages_pk PRIMARY KEY (formid);

INSERT INTO public.master_packages (
    mod,
    formid,
    "name",
    "start",
    "change",
    "end"
)
VALUES (
    'AIAgent.esp',
    '0x0004ADE7',
    'SandBoxSleep',
    '{actor} is sleeping at {location}',
    '',
    ''
)
ON CONFLICT (formid) DO NOTHING;


UPDATE public.master_packages SET formid = '0x0004ADF0' WHERE formid = '0x0004ADE7';
