@php
    use App\Support\RegistryGrAssignment;
    use App\Support\RegistrySectionByAssignment;
    use App\Support\RegistryTeamByAssignment;
@endphp

@push('scripts')
    <script type="application/json" id="hr-detail-section-map">@json(RegistrySectionByAssignment::clientMap())</script>
    <script type="application/json" id="hr-detail-standalone-sections">@json(RegistrySectionByAssignment::standaloneSections())</script>
    <script type="application/json" id="hr-detail-team-map">@json(RegistryTeamByAssignment::clientMap())</script>
    <script type="application/json" id="hr-detail-org-blocks">@json($blocks ?? [])</script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sectionMapElement = document.getElementById('hr-detail-section-map');
            const standaloneSectionsElement = document.getElementById('hr-detail-standalone-sections');
            const teamMapElement = document.getElementById('hr-detail-team-map');
            const blocksElement = document.getElementById('hr-detail-org-blocks');
            const locationSelect = document.getElementById(@json($locationFieldId ?? 'jurisdiction'));
            const form = locationSelect?.closest('form');

            if (!sectionMapElement || !standaloneSectionsElement || !teamMapElement || !blocksElement || !locationSelect) {
                return;
            }

            const sectionMap = JSON.parse(sectionMapElement.textContent || '{}');
            const standaloneSections = JSON.parse(standaloneSectionsElement.textContent || '[]');
            const teamMap = JSON.parse(teamMapElement.textContent || '{}');
            const blocks = JSON.parse(blocksElement.textContent || '[]');
            const grDepartment = @json(RegistryGrAssignment::DEPARTMENT);
            const teamParentSelections = {};

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

            const setHint = (element, message, visible = true) => {
                if (!element) {
                    return;
                }
                element.textContent = message;
                element.classList.toggle('hidden', !visible);
                element.toggleAttribute('aria-hidden', !visible);
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

            const initBlock = (block) => {
                const departmentSelect = document.getElementById(block.departmentId);
                const sectionSelect = document.getElementById(block.sectionId);
                const teamSelect = document.getElementById(block.teamId);
                const sectionHint = document.getElementById(block.sectionHintId);
                const teamHint = document.getElementById(block.teamHintId);
                const teamField = document.getElementById(block.teamFieldId);

                if (!departmentSelect || !sectionSelect || !teamSelect) {
                    return null;
                }

                const rebuildSectionOptions = () => {
                    const department = departmentSelect.value;
                    const location = locationSelect.value;
                    let options = sectionOptionsFor(department, location);
                    const previous = sectionSelect.value;
                    let keep = previous !== '' && options.includes(previous)
                        ? previous
                        : (block.initialSection !== '' && options.includes(block.initialSection) ? block.initialSection : '');

                    if (keep === '' && block.initialSection !== '' && !options.includes(block.initialSection)) {
                        options = [...options, block.initialSection];
                        keep = block.initialSection;
                    } else if (keep !== '' && !options.includes(keep)) {
                        options = [...options, keep];
                    }

                    fillSelect(sectionSelect, options, keep);

                    if (options.length === 0) {
                        sectionSelect.disabled = true;
                        setHint(sectionHint, '', false);

                        return;
                    }

                    sectionSelect.disabled = false;
                    setHint(sectionHint, '', false);
                };

                const rebuildTeamOptions = (preferredTeam = '') => {
                    const department = departmentSelect.value;
                    const location = locationSelect.value;
                    const section = sectionSelect.value;
                    let options = teamOptionsFor(department, location, section);
                    let selectedValue = preferredTeam || teamSelect.value;

                    if (!selectedValue && block.initialTeam !== '') {
                        options = resolveInitialTeamOptions(department, location, section, block.initialTeam);
                        selectedValue = block.initialTeam;
                    }

                    const parentSelection = teamParentSelections[block.suffix] ?? '';

                    if (parentSelection !== '' && teamChildOptionsFor(department, location, section, parentSelection).length > 0) {
                        options = teamChildOptionsFor(department, location, section, parentSelection);
                        if (!options.includes(selectedValue)) {
                            selectedValue = '';
                        }
                    } else if (parentSelection !== '') {
                        teamParentSelections[block.suffix] = '';
                    }

                    if (selectedValue !== '' && !options.includes(selectedValue)) {
                        options = [...options, selectedValue];
                    } else if (block.initialTeam !== '' && !options.includes(block.initialTeam)) {
                        options = [...options, block.initialTeam];
                        selectedValue = selectedValue || block.initialTeam;
                    }

                    const visible = options.length > 0;
                    teamField?.classList.toggle('hidden', !visible);

                    if (!visible) {
                        teamSelect.value = '';
                        teamParentSelections[block.suffix] = '';
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
                    rebuildTeamOptions();
                };

                departmentSelect.addEventListener('change', () => {
                    teamParentSelections[block.suffix] = '';
                    refreshOrgSelects();
                });
                sectionSelect.addEventListener('change', () => {
                    teamParentSelections[block.suffix] = '';
                    rebuildTeamOptions('');
                });
                teamSelect.addEventListener('change', () => {
                    const selected = teamSelect.value;

                    if (teamHasChildren(departmentSelect.value, locationSelect.value, sectionSelect.value, selected)) {
                        teamParentSelections[block.suffix] = selected;
                        rebuildTeamOptions('');

                        return;
                    }

                    teamParentSelections[block.suffix] = '';
                });

                teamParentSelections[block.suffix] = detectGrTeamParent(
                    departmentSelect.value,
                    locationSelect.value,
                    block.initialSection,
                    block.initialTeam,
                );

                refreshOrgSelects();

                return { sectionSelect, teamSelect, refreshOrgSelects };
            };

            const initialized = blocks.map(initBlock).filter(Boolean);

            locationSelect.addEventListener('change', () => {
                initialized.forEach((block) => {
                    block.refreshOrgSelects();
                });
            });

            form?.addEventListener('submit', () => {
                initialized.forEach((block) => {
                    block.sectionSelect.disabled = false;
                    block.teamSelect.disabled = false;
                });
            });
        });
    </script>
@endpush
