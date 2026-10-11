(() => {
    const loadOptions = async (endpoint, level, parent = "", parentLevel = "") => {
        const params = new URLSearchParams({ level });
        if (parent) params.set("parent", parent);
        if (parentLevel) params.set("parent_level", parentLevel);
        const response = await fetch(`${endpoint}?${params}`, {
            headers: { Accept: "application/json" },
            credentials: "same-origin",
        });
        if (!response.ok) throw new Error("PSGC location data is unavailable.");
        const payload = await response.json();
        if (!Array.isArray(payload.data)) throw new Error("PSGC location data is unavailable.");
        return payload.data;
    };

    const initialize = () => {
        document.querySelectorAll("[data-psgc-address]").forEach((container) => {
            if (container.dataset.psgcInitialized === "true") return;
            container.dataset.psgcInitialized = "true";

            const region = container.querySelector('[data-address-select="region"]');
            const province = container.querySelector('[data-address-select="province"]');
            const municipality = container.querySelector('[data-address-select="municipality"]');
            const barangay = container.querySelector('[data-address-select="barangay"]');
            const status = container.querySelector("[data-address-status]");
            const endpoint = container.dataset.optionsUrl;
            const selected = {
                region: container.dataset.selectedRegion,
                province: container.dataset.selectedProvince,
                municipality: container.dataset.selectedMunicipality,
                barangay: container.dataset.selectedBarangay,
            };
            const hidden = Object.fromEntries(
                ["region", "province", "municipality", "barangay"].map((key) => [
                    key,
                    container.querySelector(`[data-address-value="${key}"]`),
                ]),
            );

            const fill = (select, items, placeholder, selectedName = "") => {
                select.replaceChildren(new Option(placeholder, ""));
                items.forEach((item) => select.add(new Option(item.name, item.code)));
                if (selectedName) {
                    const match = [...select.options].find((option) => option.text === selectedName);
                    if (match) select.value = match.value;
                }
                select.disabled = items.length === 0;
            };

            const setHidden = (key, select) => {
                hidden[key].value = select.value
                    ? select.selectedOptions[0]?.textContent ?? ""
                    : "";
            };

            const clearBelowRegion = () => {
                fill(province, [], "Choose a region first");
                fill(municipality, [], "Choose a province first");
                fill(barangay, [], "Choose a city / municipality first");
                hidden.province.value = hidden.municipality.value = hidden.barangay.value = "";
            };

            const loadProvinces = async (regionCode, provinceName = "") => {
                const provinces = await loadOptions(endpoint, "provinces", regionCode);
                if (regionCode === "1300000000" && provinces.length === 0) {
                    provinces.push({ code: "NCR", name: "Metro Manila" });
                }
                fill(province, provinces, "Choose a province", provinceName);
                setHidden("province", province);
            };

            const loadLocalities = async (parentCode, parentLevel, municipalityName = "") => {
                const entries = await loadOptions(endpoint, "localities", parentCode, parentLevel);
                fill(municipality, entries, "Choose a city / municipality", municipalityName);
                setHidden("municipality", municipality);
            };

            const loadBarangays = async (localityCode, barangayName = "") => {
                const entries = await loadOptions(endpoint, "barangays", localityCode);
                fill(barangay, entries, "Choose a barangay", barangayName);
                setHidden("barangay", barangay);
            };

            region.addEventListener("change", async () => {
                clearBelowRegion();
                setHidden("region", region);
                try {
                    await loadProvinces(region.value);
                    status.textContent = "Choose a province to continue.";
                } catch {
                    status.textContent = "Location data could not load. Refresh and try again.";
                }
            });

            province.addEventListener("change", async () => {
                fill(municipality, [], "Loading cities / municipalities…");
                fill(barangay, [], "Choose a city / municipality first");
                hidden.municipality.value = hidden.barangay.value = "";
                setHidden("province", province);
                try {
                    const ncr = province.value === "NCR";
                    await loadLocalities(province.value, ncr ? "region" : "province");
                    status.textContent = "Choose a city or municipality to continue.";
                } catch {
                    status.textContent = "Location data could not load. Refresh and try again.";
                }
            });

            municipality.addEventListener("change", async () => {
                fill(barangay, [], "Loading barangays…");
                hidden.barangay.value = "";
                setHidden("municipality", municipality);
                try {
                    await loadBarangays(municipality.value);
                    status.textContent = "Choose your barangay.";
                } catch {
                    status.textContent = "Location data could not load. Refresh and try again.";
                }
            });

            barangay.addEventListener("change", () => setHidden("barangay", barangay));

            (async () => {
                try {
                    fill(region, await loadOptions(endpoint, "regions"), "Choose a region", selected.region);
                    if (!region.value) {
                        status.textContent = "Select your region to load locations.";
                        return;
                    }
                    setHidden("region", region);
                    await loadProvinces(region.value, selected.province);
                    const ncr = province.value === "NCR";
                    if (!province.value) return;
                    await loadLocalities(ncr ? region.value : province.value, ncr ? "region" : "province", selected.municipality);
                    if (!municipality.value) return;
                    await loadBarangays(municipality.value, selected.barangay);
                    status.textContent = "Address locations loaded.";
                } catch {
                    fill(region, [], "Location data unavailable");
                    status.textContent = "Location data could not load. Refresh and try again.";
                }
            })();
        });
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initialize, { once: true });
    } else {
        initialize();
    }
})();
