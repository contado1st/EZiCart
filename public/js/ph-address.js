document.addEventListener('DOMContentLoaded', () => {
    const provinceSelect = document.getElementById('province');
    const citySelect = document.getElementById('municipality');
    const barangaySelect = document.getElementById('barangay');

    if (!provinceSelect || !citySelect || !barangaySelect) return;

    const API_BASE = 'https://psgc.gitlab.io/api';

    function populateDropdown(selectElement, data, placeholder) {
        selectElement.innerHTML = `<option value="">${placeholder}</option>`;
        data.sort((a, b) => a.name.localeCompare(b.name)).forEach(item => {
            const option = document.createElement('option');
            option.value = item.name;
            option.dataset.code = item.code;
            option.textContent = item.name;
            selectElement.appendChild(option);
        });
    }

    // 1. Fetch ALL Provinces directly on page load
    fetch(`${API_BASE}/provinces/`)
        .then(res => res.json())
        .then(data => {
            populateDropdown(provinceSelect, data, 'Select Province');
            // Add Metro Manila manually since it is a Special Region, not a province
            const ncrOption = document.createElement('option');
            ncrOption.value = 'Metro Manila';
            ncrOption.dataset.code = '130000000'; // Region code for NCR
            ncrOption.textContent = 'Metro Manila';
            provinceSelect.insertBefore(ncrOption, provinceSelect.options[1]);
        })
        .catch(err => console.error('Error fetching provinces:', err));

    // 2. Province Change -> Fetch Cities/Municipalities
    provinceSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const provinceCode = selectedOption?.dataset.code;

        citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
        barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
        citySelect.disabled = true;
        barangaySelect.disabled = true;

        if (!provinceCode) return;

        // If Metro Manila (NCR) is selected
        let url = `${API_BASE}/provinces/${provinceCode}/cities-municipalities/`;
        if (provinceCode === '130000000') {
            url = `${API_BASE}/regions/${provinceCode}/cities-municipalities/`;
        }

        fetch(url)
            .then(res => res.json())
            .then(data => {
                populateDropdown(citySelect, data, 'Select City/Municipality');
                citySelect.disabled = false;
            })
            .catch(err => console.error('Error fetching cities:', err));
    });

    // 3. City Change -> Fetch Barangays
    citySelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const cityCode = selectedOption?.dataset.code;

        barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
        barangaySelect.disabled = true;

        if (!cityCode) return;

        fetch(`${API_BASE}/cities-municipalities/${cityCode}/barangays/`)
            .then(res => res.json())
            .then(data => {
                populateDropdown(barangaySelect, data, 'Select Barangay');
                barangaySelect.disabled = false;
            })
            .catch(err => console.error('Error fetching barangays:', err));
    });
});