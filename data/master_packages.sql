
CREATE TABLE public.master_packages (
    mod text NOT NULL,
    formid text NOT NULL,
    name text NOT NULL,
    start text,
    change text,
    "end" text
)
WITH (autovacuum_enabled='on', toast.autovacuum_enabled='on');


ALTER TABLE public.master_packages OWNER TO dwemer;

--
-- Data for Name: master_packages; Type: TABLE DATA; Schema: public; Owner: dwemer
--

INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x00007428', 'nwsFollowerCombatTemplate', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0000742A', 'nwsFollowerCombatPackage', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x00019663', 'nwsFollowerBoxPackTemplate', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x00019664', 'nwsFollowerBoxPackPKG', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0001BE0C', 'nwsFollowerStealthPKG', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x00025CAD', 'nwsFollowerWaitBox', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x00034A7E', 'nwsFollowerHorsePKG', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0004D714', 'nwsFollowerGoMount', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x000C44F8', 'nwsFollowerTorchTravel', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x000C44F9', 'nwsFollowerTorchUse', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x000C44FE', 'nwsFollowerTorchMake', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x000C9436', 'nwsFollowerArrowTravel', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x000C9437', 'nwsFollowerArrowUse', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x000C9438', 'nwsFollowerArrowMake', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x000CBBD2', 'nwsFollowerHomeTMP10', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x000F84CF', 'nwsFollowerSharpMake', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x000F84D0', 'nwsFollowerSharpTravel', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x000F84D1', 'nwsFollowerSharpUse', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x000FAC70', 'nwsFollowerTavernTravel', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x000FAC76', 'nwsFollowerTavernGive', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x000FAC77', 'nwsFollowerTavernDrink', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x000FAC78', 'nwsFollowerTavernWait_F', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x000FAC79', 'nwsFollowerTavernWait_W', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x00133CA0', 'nwsFollowerGiftGive', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x00145222', 'nwsPlayerHorseTravel', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0015DE5F', 'nwsFollowerPotionMake', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0015DE60', 'nwsFollowerPotionTravel', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0015DE61', 'nwsFollowerPotionUse', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0015DE63', 'nwsFollowerTravelTMP', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0016553B', 'nwsFollowerTavernWait_F2', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x001C85BD', 'nwsFollowerStealthPT', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x001D735A', 'nwsFollowerHorseTravelPKG', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x001F2701', 'nwsFollowerWaitBoxPT', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x002C7630', 'nwsFollowerLightIntPKG', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x002C9DCA', 'nwsFollowerLightExtPKG', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x002FDF45', 'nwsFollowerMinionFollow', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x00311C15', 'nwsFollowerHealPT', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x00311C16', 'nwsFollowerHealPlayer', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x00311C17', 'nwsFollowerHealSelf', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x003A64A7', 'nwsFollowerDefaultPKG', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x00406D36', 'nwsFollowerHomeTMP20', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x004094D1', 'nwsFollowerHomePackage2', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x00415AD8', 'nwsFollowerLootPT', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x00415AD9', 'nwsFollowerLootExtPKG', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0041F941', 'nwsFollowerLootIntPKG', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x004560AF', 'nwsFollowerFollow_Flee', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0045D77C', 'nwsFollowerCombatInt_Close', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0045D77D', 'nwsFollowerCombatExt_Close', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0045D77E', 'nwsFollowerCombatInt_Standard', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0045D77F', 'nwsFollowerCombatExt_Standard', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0045D780', 'nwsFollowerCombatInt_Far', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0045D781', 'nwsFollowerCombatExt_Far', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0045D782', 'nwsFollowerCombatHIntTMP', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0045D783', 'nwsFollowerCombatHExtTMP', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0045D784', 'nwsFollowerCombatHInt_Close', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0045D785', 'nwsFollowerCombatHExt_Far', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0045D786', 'nwsFollowerCombatHExt_Standard', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0045D787', 'nwsFollowerCombatHInt_Close', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0045D788', 'nwsFollowerCombatHInt_Far', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0045D789', 'nwsFollowerCombatHInt_Standard', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0045D78A', 'nwsFollowerCombatExt_WW', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0045D78B', 'nwsFollowerCombatInt_WW', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0047147A', 'nwsFollowerFollowPT', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x004FECA0', 'nwsFollowerFollowPsvPKG', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0051296F', 'nwsFollowerWaitSerana', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x00523EA3', 'nwsFollowerSparPos0', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x00523EA4', 'nwsFollowerSparPos1', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x00523EA5', 'nwsFollowerSparIdle0', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x00523EA6', 'nwsFollowerSparDrawn', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x00526678', 'nwsFollowerSparWatch0', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x00526679', 'nwsFollowerSparWatch1', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x00537BE2', 'nwsFollowerHoldFollow', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x005CE649', 'nwsFollowerFleeCBT', NULL, NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x00257FAF', 'nwsFollowerGiveGold', '{actor} gives gold to {player}', NULL, '{actor} gave gold to {player}');
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x0047147B', 'nwsFollowerFollowPKG', '{actor} is following player at {location}', NULL, '{actor} leaves following player at {location}');
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x00226725', 'nwsFollowerGoPlayer', '{actor} starts travel from {location} to meet {player}', NULL, NULL);
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x00257FA6', 'nwsFollowerSellTravel', '{actor} starts travelling from {location} to sell items for {player}', '{actor} sells items at a trader at {location}', '');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008DFE', 'vvvSleepPackage025', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008DFF', 'vvvSleepPackage026', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E00', 'vvvSleepPackage027', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('AIAgent.esp', '0x000268B0', 'ComeCloser', '{actor} walks near to {player}', '', '{actor} no longer walks near {player}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E01', 'vvvSleepPackage028', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E02', 'vvvSleepPackage029', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E03', 'vvvSleepPackage030', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E04', 'vvvSleepPackage031', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E05', 'vvvSleepPackage032', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E06', 'vvvSleepPackage033', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E07', 'vvvSleepPackage034', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E08', 'vvvSleepPackage035', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E09', 'vvvSleepPackage036', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E0A', 'vvvSleepPackage037', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E0B', 'vvvSleepPackage038', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E0C', 'vvvSleepPackage039', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E20', 'vvvSleepPackage025', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E21', 'vvvSleepPackage026', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E22', 'vvvSleepPackage027', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E23', 'vvvSleepPackage028', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E24', 'vvvSleepPackage029', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E25', 'vvvSleepPackage030', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E26', 'vvvSleepPackage031', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E27', 'vvvSleepPackage032', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E28', 'vvvSleepPackage033', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E29', 'vvvSleepPackage034', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E2A', 'vvvSleepPackage035', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E2B', 'vvvSleepPackage036', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E2C', 'vvvSleepPackage037', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E2D', 'vvvSleepPackage038', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E2E', 'vvvSleepPackage039', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E2F', 'vvvSleepPackage040', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E30', 'vvvSleepPackage041', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E31', 'vvvSleepPackage042', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E32', 'vvvSleepPackage043', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E33', 'vvvSleepPackage044', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00008E34', 'vvvSleepPackage045', '{actor} sleeps at location {location}', '', '{actor} wakes up at location {location}');
INSERT INTO public.master_packages VALUES ('nwsFollowerFramework.esp', '0x000CE38F', 'nwsFollowerHomePackage', '{actor} starts travelling home from {location}', NULL, '{actor} returned home at {location}');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00000D6D', 'vvvMarkHomePackage51', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00001D9A', 'vvvMarkHomePackage52', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00001DA3', 'vvvMarkHomePackage53', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00001DA4', 'vvvMarkHomePackage54', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00001DA5', 'vvvMarkHomePackage55', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00001DA6', 'vvvMarkHomePackage56', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00001DA7', 'vvvMarkHomePackage57', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00001DA8', 'vvvMarkHomePackage58', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00001DA9', 'vvvMarkHomePackage59', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00002887', 'vvvMarkHomePackage60', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00002888', 'vvvMarkHomePackage61', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00002889', 'vvvMarkHomePackage62', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x0000288A', 'vvvMarkHomePackage63', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x0000288B', 'vvvMarkHomePackage64', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x0000288C', 'vvvMarkHomePackage65', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x0000288D', 'vvvMarkHomePackage66', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x0000288E', 'vvvMarkHomePackage67', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x0000288F', 'vvvMarkHomePackage68', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00002890', 'vvvMarkHomePackage69', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00002891', 'vvvMarkHomePackage70', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00002892', 'vvvMarkHomePackage71', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00002893', 'vvvMarkHomePackage72', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00002894', 'vvvMarkHomePackage73', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00002895', 'vvvMarkHomePackage74', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00002896', 'vvvMarkHomePackage75', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00005935', 'vvvMarkHomePackage76', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00005936', 'vvvMarkHomePackage77', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00005937', 'vvvMarkHomePackage78', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00005938', 'vvvMarkHomePackage79', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00005939', 'vvvMarkHomePackage80', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00005EA6', 'vvvMarkHomePackage81', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00005EA7', 'vvvMarkHomePackage82', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00005EA8', 'vvvMarkHomePackage83', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00005EA9', 'vvvMarkHomePackage84', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00005EAA', 'vvvMarkHomePackage85', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00005EAB', 'vvvMarkHomePackage86', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00005EAC', 'vvvMarkHomePackage87', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00005EAD', 'vvvMarkHomePackage88', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00005EAE', 'vvvMarkHomePackage89', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x00005EAF', 'vvvMarkHomePackage90', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x000079AE', 'vvvMarkHomePackage91', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x000079AF', 'vvvMarkHomePackage92', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x000079B0', 'vvvMarkHomePackage93', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x000079B1', 'vvvMarkHomePackage94', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x000079B2', 'vvvMarkHomePackage95', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x000079B3', 'vvvMarkHomePackage96', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x000079B4', 'vvvMarkHomePackage97', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x000079B5', 'vvvMarkHomePackage98', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x000079B6', 'vvvMarkHomePackage99', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x000079B7', 'vvvMarkHomePackage100', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('My Home Is Your Home.esp', '0x000089F4', 'vvvMarkHomePackage101', '{actor} starts doing assigned duties at {location}.', '', '{actor} stops assigned duties at {location}.');
INSERT INTO public.master_packages VALUES ('AIAgent.esp', '0x0001ABFE', 'TravelTo', '{actor} starts journey from {location}', '', '{actor} stops travelling at {location}');
INSERT INTO public.master_packages VALUES ('AIAgent.esp', '0x0002226D', 'FollowPlayer', '{actor} starts follow {player}', '', '{actor} ends follow {player}');


--
-- Name: master_packages_mod_formid_idx; Type: INDEX; Schema: public; Owner: dwemer
--

CREATE UNIQUE INDEX master_packages_mod_formid_idx ON public.master_packages USING btree (mod, formid);


