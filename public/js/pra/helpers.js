(function (window) {
    const isIndexAssistant = window.location.pathname.includes('/property-index-card');

    function normalizeText(value) {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value).trim();
    }

    function buildStreetAndCityFallback(property) {
        if (!property || typeof property !== 'object') {
            return '';
        }

        const parts = [];
        const houseNumber = normalizeText(property.house_no || property.houseNo);
        if (houseNumber) {
            parts.push(houseNumber);
        }

        const street = normalizeText(property.streetName || property.street_name);
        if (street) {
            parts.push(street);
        }

        const lgaCity = normalizeText(property.lgsaOrCity || property.lgsa_or_city);
        if (lgaCity) {
            parts.push(lgaCity);
        }

        return parts.join(', ');
    }

    function resolveDescriptionField(property, derivedStreetAndCity) {
        if (!property || typeof property !== 'object') {
            return 'No description available';
        }

        const rawDescription = normalizeText(property.property_description || property.description);
        const hasMeaningfulDescription = rawDescription && rawDescription.toLowerCase() !== 'no description available';

        if (hasMeaningfulDescription) {
            return property.property_description || property.description;
        }

        if (isIndexAssistant && derivedStreetAndCity) {
            return derivedStreetAndCity;
        }

        return rawDescription || 'No description available';
    }

    function resolveLocationField(property, derivedStreetAndCity) {
        if (!property || typeof property !== 'object') {
            return 'N/A';
        }

        const rawLocation = normalizeText(property.location);
        const hasMeaningfulLocation = rawLocation && rawLocation.toLowerCase() !== 'n/a';

        if (hasMeaningfulLocation) {
            return property.location;
        }

        if (isIndexAssistant && derivedStreetAndCity) {
            return derivedStreetAndCity;
        }

        const rawDescription = normalizeText(property.property_description || property.description);
        if (rawDescription && rawDescription.toLowerCase() !== 'no description available') {
            return property.property_description || property.description;
        }

        return rawLocation || 'N/A';
    }

    function normalizeFileNumberCandidate(value) {
        if (value === null || value === undefined) {
            return '';
        }

        return String(value).trim();
    }

    function collectFileNumberCandidatesFromSource(source) {
        const candidates = [];
        const pushCandidate = (value) => {
            const normalized = normalizeFileNumberCandidate(value);
            if (!normalized) {
                return;
            }

            const lower = normalized.toLowerCase();
            if (lower === 'n/a' || lower === 'na') {
                return;
            }

            if (!candidates.includes(normalized)) {
                candidates.push(normalized);
            }
        };

        if (!source) {
            return candidates;
        }

        if (Array.isArray(source)) {
            source.forEach(pushCandidate);
            return candidates;
        }

        pushCandidate(source.mlsFNo || source.mlsfNo || source.mls_f_no);
        pushCandidate(source.mlsFileno || source.mls_fileno || source.mlsFileNumber);
        pushCandidate(source.kangisFileNo || source.kangisfileno || source.kangis_file_no);
        pushCandidate(source.kangisFileno || source.kangisFileNumber);
        pushCandidate(source.NewKANGISFileno || source.newKANGISFileno || source.newkangisfileno || source.newkangis_file_no);
        pushCandidate(source.newKangisFileno || source.newKangisFileNumber);
        pushCandidate(source.fileno || source.file_no || source.fileNumber || source.file_number);
        pushCandidate(source.primary_file_number || source.primaryFileNumber);
        pushCandidate(source.temp_fileno || source.tempFileNo || source.tempFileNumber);

        if (source.master) {
            pushCandidate(source.master.mlsFNo);
            pushCandidate(source.master.mlsFileno);
            pushCandidate(source.master.kangisFileNo);
            pushCandidate(source.master.kangisFileno);
            pushCandidate(source.master.NewKANGISFileno);
            pushCandidate(source.master.newKangisFileno);
            pushCandidate(source.master.primary_file_number);
            pushCandidate(source.master.primaryFileNumber);
            pushCandidate(source.master.temp_fileno);
        }

        if (source.record) {
            pushCandidate(source.record.file_number);
            pushCandidate(source.record.mlsFNo);
            pushCandidate(source.record.mlsFileno);
            pushCandidate(source.record.kangisFileNo);
            pushCandidate(source.record.kangisFileno);
            pushCandidate(source.record.NewKANGISFileno);
            pushCandidate(source.record.newKangisFileno);
            pushCandidate(source.record.primary_file_number);
            pushCandidate(source.record.primaryFileNumber);
            pushCandidate(source.record.temp_fileno);
        }

        return candidates;
    }

    function buildPrimarySelectionPayloadFromModal(data) {
        if (!data || typeof data !== 'object') {
            return null;
        }

        const record = data.record && typeof data.record === 'object' ? data.record : {};
        const master = data.master && typeof data.master === 'object' ? data.master : {};

        const payload = {
            fileno: normalizeFileNumberCandidate(
                data.fileNumber ||
                data.fileno ||
                data.file_number ||
                data.primary_file_number ||
                record.file_number ||
                record.primary_file_number ||
                master.primary_file_number ||
                ''
            ),
            mlsFileno: normalizeFileNumberCandidate(
                data.mlsFNo ||
                data.mlsFileno ||
                record.mlsFNo ||
                record.mlsFileno ||
                master.mlsFNo ||
                master.mlsFileno ||
                ''
            ),
            kangisFileno: normalizeFileNumberCandidate(
                data.kangisFileNo ||
                data.kangisFileno ||
                record.kangisFileNo ||
                record.kangisFileno ||
                master.kangisFileNo ||
                master.kangisFileno ||
                ''
            ),
            newKangisFileno: normalizeFileNumberCandidate(
                data.NewKANGISFileno ||
                data.newKANGISFileno ||
                data.newKangisFileno ||
                record.NewKANGISFileno ||
                record.newKANGISFileno ||
                master.NewKANGISFileno ||
                master.newKANGISFileno ||
                ''
            ),
            sourceId: data.id || data.fileId || data.file_id || record.id || master.id || null,
        };

        if (!payload.fileno) {
            payload.fileno = payload.mlsFileno || payload.kangisFileno || payload.newKangisFileno || '';
        }

        return payload;
    }

    window.PraHelpers = {
        normalizeText,
        buildStreetAndCityFallback,
        resolveDescriptionField,
        resolveLocationField,
        normalizeFileNumberCandidate,
        collectFileNumberCandidatesFromSource,
        buildPrimarySelectionPayloadFromModal,
    };
})(window);
