using System.Text.Json;
using System.Text.Json.Serialization;
using Mutagen.Bethesda;
using Mutagen.Bethesda.Plugins;
using Mutagen.Bethesda.Plugins.Records;
using Mutagen.Bethesda.Skyrim;

var options = Options.Parse(args);
if (!Directory.Exists(options.DataPath))
{
    Console.Error.WriteLine($"Skyrim data directory not found: {options.DataPath}");
    return 2;
}

var pluginNames = LoadPluginNames(options);
if (pluginNames.Count == 0)
{
    Console.Error.WriteLine("No plugin files were selected.");
    return 2;
}

var catalog = new CatalogBuilder();
foreach (var pluginName in pluginNames)
{
    var pluginPath = Path.Combine(options.DataPath, pluginName);
    if (!File.Exists(pluginPath))
    {
        Console.Error.WriteLine($"Skipping missing plugin: {pluginName}");
        continue;
    }

    Console.Error.WriteLine($"Reading {pluginName}");
    var mod = SkyrimMod.CreateFromBinaryOverlay(pluginPath, SkyrimRelease.SkyrimSE);
    catalog.Add(mod, pluginName);
}

var manifest = catalog.Build(pluginNames);
var jsonOptions = new JsonSerializerOptions
{
    WriteIndented = true,
    DefaultIgnoreCondition = JsonIgnoreCondition.WhenWritingNull,
    PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
};
Directory.CreateDirectory(Path.GetDirectoryName(Path.GetFullPath(options.OutputPath))!);
File.WriteAllText(options.OutputPath, JsonSerializer.Serialize(manifest, jsonOptions));
Console.Error.WriteLine($"Wrote {manifest.Assets.Count} assets and {manifest.Groups.Count} groups to {options.OutputPath}");
return 0;

static List<string> LoadPluginNames(Options options)
{
    if (!string.IsNullOrWhiteSpace(options.LoadOrderPath) && File.Exists(options.LoadOrderPath))
    {
        return File.ReadLines(options.LoadOrderPath)
            .Select(line => line.Trim().TrimStart('*'))
            .Where(line => line.Length > 0 && !line.StartsWith('#'))
            .Where(line => line.EndsWith(".esm", StringComparison.OrdinalIgnoreCase)
                || line.EndsWith(".esl", StringComparison.OrdinalIgnoreCase)
                || line.EndsWith(".esp", StringComparison.OrdinalIgnoreCase))
            .Distinct(StringComparer.OrdinalIgnoreCase)
            .ToList();
    }

    var priority = new[] { "Skyrim.esm", "Update.esm", "Dawnguard.esm", "HearthFires.esm", "Dragonborn.esm" };
    var discovered = Directory.EnumerateFiles(options.DataPath)
        .Where(path => path.EndsWith(".esm", StringComparison.OrdinalIgnoreCase)
            || path.EndsWith(".esl", StringComparison.OrdinalIgnoreCase)
            || path.EndsWith(".esp", StringComparison.OrdinalIgnoreCase))
        .Select(Path.GetFileName)
        .Where(name => name is not null)
        .Cast<string>()
        .OrderBy(name => name, StringComparer.OrdinalIgnoreCase)
        .ToList();
    return priority.Where(name => discovered.Contains(name, StringComparer.OrdinalIgnoreCase))
        .Concat(discovered.Where(name => !priority.Contains(name, StringComparer.OrdinalIgnoreCase)))
        .Distinct(StringComparer.OrdinalIgnoreCase)
        .ToList();
}

sealed record Options(string DataPath, string OutputPath, string? LoadOrderPath)
{
    public static Options Parse(string[] args)
    {
        string? Get(string name)
        {
            var index = Array.FindIndex(args, value => value.Equals(name, StringComparison.OrdinalIgnoreCase));
            return index >= 0 && index + 1 < args.Length ? args[index + 1] : null;
        }

        var dataPath = Get("--data")
            ?? @"C:\Program Files (x86)\Steam\steamapps\common\Skyrim Special Edition\Data";
        var outputPath = Get("--output") ?? Path.Combine(Environment.CurrentDirectory, "chim-quest-assets.json");
        return new Options(dataPath, outputPath, Get("--load-order"));
    }
}

sealed class CatalogBuilder
{
    private readonly Dictionary<FormKey, Asset> _assets = [];
    private readonly Dictionary<string, Group> _groups = new(StringComparer.OrdinalIgnoreCase);
    private readonly Dictionary<uint, string> _humanoidRaces = new()
    {
        [0x13740] = "argonian", [0x13741] = "breton", [0x13742] = "dark_elf",
        [0x13743] = "high_elf", [0x13744] = "imperial", [0x13745] = "khajiit",
        [0x13746] = "nord", [0x13747] = "orc", [0x13748] = "redguard", [0x13749] = "wood_elf",
    };

    public void Add(ISkyrimModGetter mod, string winningPlugin)
    {
        foreach (var record in mod.Outfits) AddOutfit(record, winningPlugin);
        foreach (var record in mod.Npcs) AddNpc(record, winningPlugin);
        foreach (var record in mod.Weapons) AddItem(record, winningPlugin, "WEAP", SuggestedWeaponGroup(record.EditorID));
        foreach (var record in mod.Armors) AddItem(record, winningPlugin, "ARMO", SuggestedArmorGroup(record.EditorID));
        foreach (var record in mod.Ingestibles) AddItem(record, winningPlugin, "ALCH", SuggestedIngestibleGroup(record.EditorID));
        foreach (var record in mod.Ingredients) AddItem(record, winningPlugin, "INGR", "ingredient");
        foreach (var record in mod.Books) AddItem(record, winningPlugin, "BOOK", "book");
        foreach (var record in mod.Scrolls) AddItem(record, winningPlugin, "SCRL", "scroll");
        foreach (var record in mod.SoulGems) AddItem(record, winningPlugin, "SLGM", "soul_gem");
        foreach (var record in mod.MiscItems) AddItem(record, winningPlugin, "MISC", SuggestedMiscGroup(record.EditorID));
    }

    public Manifest Build(IReadOnlyList<string> plugins)
    {
        var liveRefs = _assets.Values.Select(asset => asset.StableRef).ToHashSet(StringComparer.OrdinalIgnoreCase);
        foreach (var group in _groups.Values)
        {
            group.Members.RemoveAll(member => !liveRefs.Contains(member.StableRef));
        }

        return new Manifest(
            "chim.quest-assets.v1",
            new Pack("local_load_order_candidates", "Local Load Order Candidates", "SkyrimSE", "1", plugins,
                "CHIM Quest Asset Exporter", "Generated candidates. Review assets before enabling this pack.", false),
            _assets.Values.OrderBy(asset => asset.SourcePlugin).ThenBy(asset => asset.EditorId).ToList(),
            _groups.Values.Where(group => group.Members.Count > 0)
                .OrderBy(group => group.Dataset).ThenBy(group => group.Key).ToList());
    }

    private void AddOutfit(IOutfitGetter record, string winningPlugin)
    {
        var stableRef = StableRef(record.FormKey);
        var itemRefs = record.Items?.Select(item => StableRef(item.FormKey)).ToList() ?? [];
        var metadata = new Dictionary<string, object?> { ["items"] = itemRefs, ["item_count"] = itemRefs.Count };
        var asset = AssetFor(record.FormKey, "OTFT", record.EditorID, "", winningPlugin, metadata, "review");
        _assets[record.FormKey] = asset;
        foreach (var group in SuggestedOutfitGroups(record.EditorID)) AddMember("outfit", group, stableRef);
    }

    private void AddNpc(INpcGetter record, string winningPlugin)
    {
        var config = record.Configuration;
        var race = ResolveRace(record.Race?.FormKey ?? FormKey.Null);
        var gender = config?.Flags.HasFlag(NpcConfiguration.Flag.Female) == true ? "female" : "male";
        var scriptCount = record.VirtualMachineAdapter?.Scripts.Count ?? 0;
        var packageCount = record.Packages?.Count ?? 0;
        var factionCount = record.Factions?.Count ?? 0;
        var flags = config?.Flags.ToString() ?? "";
        var template = record.Template?.IsNull == false ? StableRef(record.Template.FormKey) : "";
        var rejectionReasons = new List<string>();
        if (config is null) rejectionReasons.Add("missing_configuration");
        if (scriptCount > 0) rejectionReasons.Add("has_scripts");
        if (packageCount > 0) rejectionReasons.Add("has_packages");
        if (!string.IsNullOrEmpty(template)) rejectionReasons.Add("inherits_template_data");
        if (config?.Flags.HasFlag(NpcConfiguration.Flag.Unique) == true) rejectionReasons.Add("unique");
        if (config?.Flags.HasFlag(NpcConfiguration.Flag.Essential) == true) rejectionReasons.Add("essential");
        if (config?.Flags.HasFlag(NpcConfiguration.Flag.Protected) == true) rejectionReasons.Add("protected");
        if (config?.Flags.HasFlag(NpcConfiguration.Flag.IsGhost) == true) rejectionReasons.Add("ghost");
        if (record.EditorID?.Contains("test", StringComparison.OrdinalIgnoreCase) == true) rejectionReasons.Add("test_record");

        var metadata = new Dictionary<string, object?>
        {
            ["race"] = race,
            ["race_ref"] = record.Race is null ? "" : StableRef(record.Race.FormKey),
            ["gender"] = gender,
            ["voice_ref"] = record.Voice?.IsNull == false ? StableRef(record.Voice.FormKey) : "",
            ["head_part_count"] = record.HeadParts?.Count ?? 0,
            ["script_count"] = scriptCount,
            ["package_count"] = packageCount,
            ["faction_count"] = factionCount,
            ["template_ref"] = template,
            ["flags"] = flags,
            ["rejection_reasons"] = rejectionReasons,
        };
        var status = rejectionReasons.Count == 0 ? "review" : "rejected";
        var asset = AssetFor(record.FormKey, "NPC_", record.EditorID, record.Name?.String ?? "", winningPlugin, metadata, status);
        _assets[record.FormKey] = asset;
        if (!string.IsNullOrEmpty(race) && (record.HeadParts?.Count ?? 0) > 0)
        {
            AddMember("npc_templates", $"{gender}_{race}", asset.StableRef);
            AddMember("npc_own_templates", $"candidate_{gender}_{race}", asset.StableRef, active: false);
        }
    }

    private void AddItem<T>(T record, string winningPlugin, string signature, string? group)
        where T : class, IMajorRecordGetter
    {
        if (string.IsNullOrWhiteSpace(group)) return;
        var displayName = record switch
        {
            IWeaponGetter value => value.Name?.String,
            IArmorGetter value => value.Name?.String,
            IIngestibleGetter value => value.Name?.String,
            IIngredientGetter value => value.Name?.String,
            IBookGetter value => value.Name?.String,
            IScrollGetter value => value.Name?.String,
            ISoulGemGetter value => value.Name?.String,
            IMiscItemGetter value => value.Name?.String,
            _ => "",
        } ?? "";
        if (string.IsNullOrWhiteSpace(displayName)) return;

        var nonPlayable = record switch
        {
            IWeaponGetter value => value.MajorFlags.HasFlag(Weapon.MajorFlag.NonPlayable),
            IArmorGetter value => value.MajorFlags.HasFlag(Armor.MajorFlag.NonPlayable),
            _ => false,
        };
        var scriptCount = record switch
        {
            IWeaponGetter value => value.VirtualMachineAdapter?.Scripts.Count ?? 0,
            IArmorGetter value => value.VirtualMachineAdapter?.Scripts.Count ?? 0,
            IIngredientGetter value => value.VirtualMachineAdapter?.Scripts.Count ?? 0,
            IBookGetter value => value.VirtualMachineAdapter?.Scripts.Count ?? 0,
            IMiscItemGetter value => value.VirtualMachineAdapter?.Scripts.Count ?? 0,
            _ => 0,
        };
        var metadata = new Dictionary<string, object?> { ["non_playable"] = nonPlayable, ["script_count"] = scriptCount };
        var status = nonPlayable || scriptCount > 0 ? "rejected" : "review";
        var asset = AssetFor(record.FormKey, signature, record.EditorID, displayName, winningPlugin, metadata, status);
        _assets[record.FormKey] = asset;
        AddMember("item_types", group!, asset.StableRef);
        if (signature == "WEAP") AddMember("weapons", group!, asset.StableRef);
    }

    private Asset AssetFor(FormKey formKey, string signature, string? editorId, string displayName,
        string winningPlugin, Dictionary<string, object?> metadata, string safetyStatus)
    {
        var sourcePlugin = formKey.ModKey.FileName.String ?? "";
        return new Asset(StableRef(formKey), signature, editorId ?? "", displayName, sourcePlugin,
            winningPlugin, metadata, safetyStatus, true);
    }

    private void AddMember(string dataset, string key, string stableRef, bool active = true)
    {
        var normalizedKey = key.ToLowerInvariant();
        var identity = $"{dataset}|{normalizedKey}";
        if (!_groups.TryGetValue(identity, out var group))
        {
            group = new Group(dataset, normalizedKey, Title(normalizedKey), "Exporter-suggested group. Review before use.",
                new Dictionary<string, object?>(), false, []);
            _groups[identity] = group;
        }
        if (!group.Members.Any(member => member.StableRef.Equals(stableRef, StringComparison.OrdinalIgnoreCase)))
            group.Members.Add(new Member(stableRef, 1, new Dictionary<string, object?>(), "", active));
    }

    private string ResolveRace(FormKey formKey)
    {
        return formKey.ModKey.FileName.String?.Equals("Skyrim.esm", StringComparison.OrdinalIgnoreCase) == true
            && _humanoidRaces.TryGetValue(formKey.ID, out var race) ? race : "";
    }

    private static IEnumerable<string> SuggestedOutfitGroups(string? editorId)
    {
        var value = editorId ?? "";
        var rules = new (string Key, string[] Terms)[]
        {
            ("blacksmith", ["blacksmith"]), ("miner", ["miner"]), ("hunter", ["hunter"]),
            ("beggar", ["beggar"]), ("farmer", ["farmclothes"]), ("merchant", ["merchant"]),
            ("guard", ["guardoutfit", "guardimperial", "holdguard"]), ("imperial", ["imperial"]),
            ("stormcloak", ["stormcloak"]), ("forsworn", ["forsworn"]), ("bandit", ["banditarmor"]),
            ("mage", ["mage", "college", "warlock"]), ("priest", ["monk", "priest", "templeoutfit"]),
            ("thalmor", ["thalmor"]), ("vampire", ["vampireoutfit", "volkihar"]),
            ("dawnguard", ["dawnguard"]), ("skaal", ["skaaloutfit"]), ("cultist", ["cultistoutfit"]),
            ("rogue", ["thiefoutfit"]),
        };
        foreach (var rule in rules)
            if (rule.Terms.Any(term => value.Contains(term, StringComparison.OrdinalIgnoreCase))) yield return rule.Key;
    }

    private static string? SuggestedWeaponGroup(string? editorId)
    {
        var value = editorId ?? "";
        if (value.Contains("dagger", StringComparison.OrdinalIgnoreCase)) return "dagger";
        if (value.Contains("greatsword", StringComparison.OrdinalIgnoreCase)) return "greatsword";
        if (value.Contains("battleaxe", StringComparison.OrdinalIgnoreCase)) return "battleaxe";
        if (value.Contains("waraxe", StringComparison.OrdinalIgnoreCase)) return "war_axe";
        if (value.Contains("crossbow", StringComparison.OrdinalIgnoreCase)) return "crossbow";
        if (value.Contains("bow", StringComparison.OrdinalIgnoreCase)) return "bow";
        if (value.Contains("mace", StringComparison.OrdinalIgnoreCase)) return "mace";
        if (value.Contains("staff", StringComparison.OrdinalIgnoreCase)) return "staff";
        if (value.Contains("sword", StringComparison.OrdinalIgnoreCase)) return "sword";
        return null;
    }

    private static string? SuggestedArmorGroup(string? editorId)
    {
        var value = editorId ?? "";
        if (value.Contains("shield", StringComparison.OrdinalIgnoreCase)) return "shield";
        if (value.Contains("helmet", StringComparison.OrdinalIgnoreCase) || value.Contains("hat", StringComparison.OrdinalIgnoreCase)) return "helmet";
        if (value.Contains("boots", StringComparison.OrdinalIgnoreCase) || value.Contains("shoes", StringComparison.OrdinalIgnoreCase)) return "boots";
        if (value.Contains("gauntlets", StringComparison.OrdinalIgnoreCase) || value.Contains("gloves", StringComparison.OrdinalIgnoreCase)) return "gauntlets";
        if (value.Contains("clothes", StringComparison.OrdinalIgnoreCase) || value.Contains("robe", StringComparison.OrdinalIgnoreCase)) return "clothing";
        if (value.Contains("armor", StringComparison.OrdinalIgnoreCase) || value.Contains("cuirass", StringComparison.OrdinalIgnoreCase)) return "armor";
        return null;
    }

    private static string SuggestedIngestibleGroup(string? editorId)
    {
        var value = editorId ?? "";
        if (value.Contains("food", StringComparison.OrdinalIgnoreCase)) return "food";
        if (value.Contains("damage", StringComparison.OrdinalIgnoreCase) || value.Contains("poison", StringComparison.OrdinalIgnoreCase)
            || value.Contains("paralyze", StringComparison.OrdinalIgnoreCase)) return "poison";
        return "potion";
    }

    private static string? SuggestedMiscGroup(string? editorId)
    {
        var value = editorId ?? "";
        if (value.Contains("gem", StringComparison.OrdinalIgnoreCase)) return "gem";
        return value.Contains("gold", StringComparison.OrdinalIgnoreCase) ? null : "miscellaneous";
    }

    private static string StableRef(FormKey formKey)
        => $"{formKey.ModKey.FileName.String}|{formKey.ID:X6}";
    private static string Title(string key)
        => string.Join(' ', key.Split('_').Select(part => char.ToUpperInvariant(part[0]) + part[1..]));
}

sealed record Manifest(string Schema, Pack Pack, List<Asset> Assets, List<Group> Groups);
sealed record Pack(string Key, string Label, string Game, string Version, IReadOnlyList<string> RequiredPlugins,
    string Source, string Note, bool Active);
sealed record Asset(string StableRef, string Signature, string EditorId, string DisplayName, string SourcePlugin,
    string WinningPlugin, Dictionary<string, object?> Metadata, string SafetyStatus, bool Active);
sealed record Group(string Dataset, string Key, string Label, string Description,
    Dictionary<string, object?> SelectionPolicy, bool Active, List<Member> Members);
sealed record Member(string StableRef, int Weight, Dictionary<string, object?> Constraints, string Note, bool Active);
