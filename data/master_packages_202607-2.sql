

--
-- Data for Name: master_packages; Type: TABLE DATA; Schema: public; Owner: dwemer
--

INSERT INTO public.master_packages (
    plugin_name,
    formid,
    editor_id,
    description,
    condition,
    notes
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
