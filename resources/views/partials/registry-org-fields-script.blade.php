@php
    use App\Support\RegistryGrAssignment;
    use App\Support\RegistrySectionByAssignment;
    use App\Support\RegistryTeamByAssignment;

    $splitSectionTeam = $splitSectionTeam ?? false;
    $sectionRequiredForGr = $sectionRequiredForGr ?? false;
@endphp

@push('scripts')
    <script type="application/json" id="registry-section-map">@json(RegistrySectionByAssignment::clientMap())</script>
    <script type="application/json" id="registry-standalone-sections">@json(RegistrySectionByAssignment::standaloneSections())</script>
    <script type="application/json" id="registry-team-map">@json(RegistryTeamByAssignment::clientMap())</script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sectionMapElement = document.getElementById('registry-section-map');
            const standaloneSectionsElement = document.getElementById('registry-standalone-sections');
            const teamMapElement = document.getElementById('registry-team-map');
            const departmentSelect = document.getElementById('department');
            const locationSelect = document.getElementById('location');
            const sectionSelect = document.getElementById('section');
            const teamSelect = document.getElementById('team');
            const sectionHint = document.getElementById('registry-section-hint');
            const teamHint = document.getElementById('registry-team-hint');
            const sectionRequiredMark = document.getElementById('registry-section-required-mark');
            const form = departmentSelect?.closest('form');
            const splitSectionTeam = @json($splitSectionTeam);
            const sectionRequiredForGr = @json($sectionRequiredForGr);
            const grDepartment = @json(RegistryGrAssignment::DEPARTMENT);

            if (!sectionMapElement || !standaloneSectionsElement || !teamMapElement || !departmentSelect || !locationSelect || !sectionSelect) {
                return;
            }

            if (splitSectionTeam && !teamSelect) {
                return;
            }

            const teamField = document.getElementById('registry-team-field');
            const sectionMap = JSON.parse(sectionMapElement.textContent || '{}');
            const standaloneSections = JSON.parse(standaloneSectionsElement.textContent || '[]');
            const teamMap = JSON.parse(teamMapElement.textContent || '{}');
            const initialSection = @json($selectedSection ?? '');
            const initialTeam = @json($selectedTeam ?? '');
            let teamParentSelection = '';

            const sectionOptionsFor = (department, location) => {
                const rules = sectionMap[department];
                if (!rules) {
                    return standaloneSections;
                }
                if (rules['*']) {
                    return rules['*'];
                }
                if (!location || !rules[location]) {
                    return [];
                }
                return rules[location];
            };

            const grTeamRules = (location, division) => teamMap.gr?.teams?.[location]?.[division] ?? null;

            const flattenGrTeamRules = (rules) => {
                if (!rules) {
                    return [];
                }

                const values = [];
                Object.entries(rules).forEach(([label, rule]) => {
                    if (typeof rule === 'string') {
                        values.push(label);

                        return;
                    }

                    values.push(label);
                    Object.keys(rule.children ?? {}).forEach((childLabel) => values.push(childLabel));
                });

                return values;
            };

            const teamOptionsFor = (department, location, section) => {
                if (teamMap.departmentTeams[department]) {
                    return teamMap.departmentTeams[department];
                }
                if (department !== grDepartment || !location || !section) {
                    return [];
                }
                const rules = grTeamRules(location, section);
                if (!rules) {
                    return [];
                }

                return Object.keys(rules);
            };

            const teamChildOptionsFor = (department, location, section, parent) => {
                if (department !== grDepartment) {
                    return [];
                }
                const rules = grTeamRules(location, section);
                const rule = rules?.[parent];
                if (!rule || typeof rule === 'string') {
                    return [];
                }

                return Object.keys(rule.children ?? {});
            };

            const detectGrTeamParent = (department, location, section, team) => {
                if (department !== grDepartment || !team) {
                    return '';
                }

                const rules = grTeamRules(location, section);

                if (!rules) {
                    return '';
                }

                for (const [parent, rule] of Object.entries(rules)) {
                    if (typeof rule !== 'string' && Object.prototype.hasOwnProperty.call(rule.children ?? {}, team)) {
                        return parent;
                    }
                }

                return '';
            };

            const teamHasChildren = (department, location, section, parent) => {
                return teamChildOptionsFor(department, location, section, parent).length > 0;
            };

            const resolveInitialTeamOptions = (department, location, section, team) => {
                const topLevel = teamOptionsFor(department, location, section);
                if (!team || topLevel.includes(team)) {
                    return topLevel;
                }
                const valid = teamMap.departmentTeams[department]
                    ?? flattenGrTeamRules(grTeamRules(location, section));
                if (valid.includes(team)) {
                    return [...topLevel, team];
                }
                return topLevel;
            };

            const resolveTeamOptions = (department, location, section, preferredTeam = '') => {
                let options = teamOptionsFor(department, location, section);
                let selectedValue = preferredTeam || teamSelect.value;

                if (!selectedValue && initialTeam !== '') {
                    options = resolveInitialTeamOptions(department, location, section, initialTeam);
                    selectedValue = initialTeam;
                }

                if (teamParentSelection !== '' && teamChildOptionsFor(department, location, section, teamParentSelection).length > 0) {
                    options = teamChildOptionsFor(department, location, section, teamParentSelection);
                    if (!options.includes(selectedValue)) {
                        selectedValue = '';
                    }
                } else if (teamParentSelection !== '') {
                    teamParentSelection = '';
                }

                if (selectedValue !== '' && !options.includes(selectedValue)) {
                    options = [...options, selectedValue];
                } else if (initialTeam !== '' && !options.includes(initialTeam)) {
                    options = [...options, initialTeam];
                    selectedValue = selectedValue || initialTeam;
                }

                return { options, selectedValue };
            };

            const setHint = (element, message, visible = true) => {
                if (!element) {
                    return;
                }
                element.textContent = message;
                element.classList.toggle('hidden', !visible);
                element.toggleAttribute('aria-hidden', !visible);
            };

            const updateSectionRequiredUi = () => {
                if (!sectionRequiredForGr) {
                    return;
                }

                const required = departmentSelect.value === grDepartment && !sectionSelect.disabled;
                sectionRequiredMark?.classList.toggle('hidden', !required);
                sectionSelect.toggleAttribute('required', required);
            };

            const fillSelect = (select, options, selectedValue) => {
                select.innerHTML = '';
                const blank = document.createElement('option');
                blank.value = '';
                blank.textContent = '選択してください';
                select.appendChild(blank);
                options.forEach((label) => {
                    const option = document.createElement('option');
                    option.value = label;
                    option.textContent = label;
                    if (label === selectedValue) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
            };

            const rebuildSectionOptions = () => {
                const department = departmentSelect.value;
                const location = locationSelect.value;
                let options = sectionOptionsFor(department, location);

                if (!splitSectionTeam && teamMap.departmentTeams[department]) {
                    options = [...new Set([...options, ...teamMap.departmentTeams[department]])];
                }

                const previous = sectionSelect.value;
                let keep = previous !== '' && options.includes(previous)
                    ? previous
                    : (initialSection !== '' && options.includes(initialSection) ? initialSection : '');

                if (keep === '' && initialSection !== '' && !options.includes(initialSection)) {
                    options = [...options, initialSection];
                    keep = initialSection;
                } else if (keep !== '' && !options.includes(keep)) {
                    options = [...options, keep];
                }

                fillSelect(sectionSelect, options, keep);

                if (options.length === 0) {
                    sectionSelect.disabled = true;
                    setHint(sectionHint, '', false);
                    updateSectionRequiredUi();

                    return;
                }

                sectionSelect.disabled = false;
                setHint(sectionHint, '', false);
                updateSectionRequiredUi();
            };

            const rebuildTeamOptions = (preferredTeam = '') => {
                if (!splitSectionTeam || !teamSelect) {
                    return;
                }

                const department = departmentSelect.value;
                const location = locationSelect.value;
                const section = sectionSelect.value;
                const { options, selectedValue } = resolveTeamOptions(
                    department,
                    location,
                    section,
                    preferredTeam,
                );

                const visible = options.length > 0;
                teamField?.classList.toggle('hidden', !visible);

                if (!visible) {
                    teamSelect.value = '';
                    teamParentSelection = '';
                    teamSelect.disabled = true;
                    setHint(teamHint, '', false);

                    return;
                }

                fillSelect(teamSelect, options, selectedValue);
                teamSelect.disabled = false;
                setHint(teamHint, '', false);
            };

            const refreshOrgSelects = () => {
                rebuildSectionOptions();
                if (splitSectionTeam) {
                    rebuildTeamOptions();
                }
            };

            if (splitSectionTeam && teamSelect) {
                teamSelect.addEventListener('change', () => {
                    const department = departmentSelect.value;
                    const location = locationSelect.value;
                    const section = sectionSelect.value;
                    const selected = teamSelect.value;

                    if (teamHasChildren(department, location, section, selected)) {
                        teamParentSelection = selected;
                        rebuildTeamOptions('');

                        return;
                    }

                    teamParentSelection = '';
                });
            }

            departmentSelect.addEventListener('change', () => {
                teamParentSelection = '';
                refreshOrgSelects();
            });
            locationSelect.addEventListener('change', () => {
                teamParentSelection = '';
                refreshOrgSelects();
            });
            sectionSelect.addEventListener('change', () => {
                teamParentSelection = '';
                if (splitSectionTeam) {
                    rebuildTeamOptions('');
                }
            });

            form?.addEventListener('submit', () => {
                sectionSelect.disabled = false;
                if (splitSectionTeam && teamSelect) {
                    teamSelect.disabled = false;
                }
            });

            if (splitSectionTeam) {
                teamParentSelection = detectGrTeamParent(
                    departmentSelect.value,
                    locationSelect.value,
                    initialSection,
                    initialTeam,
                );
            }

            refreshOrgSelects();
            updateSectionRequiredUi();
        });
    </script>
@endpush
