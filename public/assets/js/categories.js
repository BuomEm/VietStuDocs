/**
 * Categories Cascade Selection Handler
 * Handles the cascade selection: Cấp học → Lớp/Nhóm ngành → Môn học/Ngành → Loại tài liệu
 */

const CategoriesManager = {
    apiEndpoint: '/handler/categories_api.php',
    
    // Cache for API responses
    cache: {
        grades: {},
        subjects: {},
        majorGroups: null,
        majors: {},
        docTypes: {}
    },
    
    // Current selection state
    state: {
        educationLevel: null,
        gradeId: null,
        subjectCode: null,
        majorGroupId: null,
        majorCode: null,
        docTypeCode: null
    },
    
    // DOM element IDs
    elements: {
        educationLevel: 'education_level',
        gradeContainer: 'grade_container',
        grade: 'grade_id',
        subjectContainer: 'subject_container',
        subject: 'subject_code',
        majorGroupContainer: 'major_group_container',
        majorGroup: 'major_group_id',
        majorContainer: 'major_container',
        major: 'major_code',
        docTypeContainer: 'doc_type_container',
        docType: 'doc_type_code'
    },
    
    /**
     * Initialize the cascade selection
     */
    init: function(options = {}) {
        // Override element IDs if provided
        if (options.elements) {
            this.elements = { ...this.elements, ...options.elements };
        }
        
        // Pre-populate values if provided (for edit mode)
        if (options.values) {
            this.state = { ...this.state, ...options.values };
        }
        
        // Bind event listeners
        this.bindEvents();
        
        // If we have pre-populated values, load the cascade
        if (this.state.educationLevel) {
            this.loadCascadeFromState();
        }
    },
    
    /**
     * Bind change events to select elements
     */
    bindEvents: function() {
        const self = this;
        
        // Education Level change
        const eduSelect = document.getElementById(this.elements.educationLevel);
        if (eduSelect) {
            eduSelect.addEventListener('change', function() {
                self.onEducationLevelChange(this.value);
            });
        }
        
        // Grade change
        const gradeSelect = document.getElementById(this.elements.grade);
        if (gradeSelect) {
            gradeSelect.addEventListener('change', function() {
                self.onGradeChange(this.value);
            });
        }
        
        // Subject change
        const subjectSelect = document.getElementById(this.elements.subject);
        if (subjectSelect) {
            subjectSelect.addEventListener('change', function() {
                self.state.subjectCode = this.value;
            });
        }
        
        // Major Group change
        const majorGroupSelect = document.getElementById(this.elements.majorGroup);
        if (majorGroupSelect) {
            majorGroupSelect.addEventListener('change', function() {
                self.onMajorGroupChange(this.value);
            });
        }
        
        // Major change
        const majorSelect = document.getElementById(this.elements.major);
        if (majorSelect) {
            majorSelect.addEventListener('change', function() {
                self.state.majorCode = this.value;
            });
        }
        
        // Doc Type change
        const docTypeSelect = document.getElementById(this.elements.docType);
        if (docTypeSelect) {
            docTypeSelect.addEventListener('change', function() {
                self.state.docTypeCode = this.value;
            });
        }
    },
    
    /**
     * Handle education level change
     */
    onEducationLevelChange: async function(level) {
        this.state.educationLevel = level;
        this.resetSubsequentSelections('educationLevel');
        
        if (!level) {
            this.hideAllContainers();
            return;
        }
        
        const isPhoThong = ['tieu_hoc', 'thcs', 'thpt'].includes(level);
        
        if (isPhoThong) {
            // Show grade, hide major group
            this.showContainer(this.elements.gradeContainer);
            this.hideContainer(this.elements.majorGroupContainer);
            this.hideContainer(this.elements.majorContainer);
            
            // Load grades
            await this.loadGrades(level);
        } else {
            // Show major group, hide grade
            this.hideContainer(this.elements.gradeContainer);
            this.hideContainer(this.elements.subjectContainer);
            this.showContainer(this.elements.majorGroupContainer);
            
            // Load major groups
            await this.loadMajorGroups();
        }
        
        // Load doc types
        await this.loadDocTypes(level);
        this.showContainer(this.elements.docTypeContainer);
    },
    
    /**
     * Handle grade change
     */
    onGradeChange: async function(gradeId) {
        this.state.gradeId = gradeId;
        this.resetSubsequentSelections('grade');
        
        if (!gradeId) {
            this.hideContainer(this.elements.subjectContainer);
            return;
        }
        
        // Load subjects for this grade
        await this.loadSubjects(this.state.educationLevel, gradeId);
        this.showContainer(this.elements.subjectContainer);
    },
    
    /**
     * Handle major group change
     */
    onMajorGroupChange: async function(groupId) {
        this.state.majorGroupId = groupId;
        this.resetSubsequentSelections('majorGroup');
        
        if (!groupId) {
            this.hideContainer(this.elements.majorContainer);
            return;
        }
        
        // Load majors for this group
        await this.loadMajors(groupId);
        this.showContainer(this.elements.majorContainer);
    },
    
    /**
     * Load grades from API
     */
    loadGrades: async function(level) {
        const cacheKey = level;
        
        if (this.cache.grades[cacheKey]) {
            this.populateSelect(this.elements.grade, this.cache.grades[cacheKey], 'id', 'name', '-- Chọn lớp --');
            return;
        }
        
        try {
            const response = await fetch(`${this.apiEndpoint}?action=grades&level=${level}`);
            const data = await response.json();
            
            if (data.success) {
                this.cache.grades[cacheKey] = data.data;
                this.populateSelect(this.elements.grade, data.data, 'id', 'name', '-- Chọn lớp --');
            }
        } catch (error) {
            console.error('Error loading grades:', error);
        }
    },
    
    /**
     * Load subjects from API
     */
    loadSubjects: async function(level, gradeId) {
        const cacheKey = `${level}_${gradeId}`;
        
        if (this.cache.subjects[cacheKey]) {
            this.populateSelect(this.elements.subject, this.cache.subjects[cacheKey], 'code', 'name', '-- Chọn môn học --');
            return;
        }
        
        try {
            const response = await fetch(`${this.apiEndpoint}?action=subjects&level=${level}&grade_id=${gradeId}`);
            const data = await response.json();
            
            if (data.success) {
                this.cache.subjects[cacheKey] = data.data;
                this.populateSelect(this.elements.subject, data.data, 'code', 'name', '-- Chọn môn học --');
            }
        } catch (error) {
            console.error('Error loading subjects:', error);
        }
    },
    
    /**
     * Load major groups from API
     */
    loadMajorGroups: async function() {
        if (this.cache.majorGroups) {
            this.populateSelect(this.elements.majorGroup, this.cache.majorGroups, 'id', 'name', '-- Chọn nhóm ngành --');
            return;
        }
        
        try {
            const response = await fetch(`${this.apiEndpoint}?action=major_groups`);
            const data = await response.json();
            
            if (data.success) {
                this.cache.majorGroups = data.data;
                this.populateSelect(this.elements.majorGroup, data.data, 'id', 'name', '-- Chọn nhóm ngành --');
            }
        } catch (error) {
            console.error('Error loading major groups:', error);
        }
    },
    
    /**
     * Load majors from API
     */
    loadMajors: async function(groupId) {
        const cacheKey = groupId;
        
        if (this.cache.majors[cacheKey]) {
            this.populateSelect(this.elements.major, this.cache.majors[cacheKey], 'code', 'name', '-- Chọn ngành học --');
            return;
        }
        
        try {
            const response = await fetch(`${this.apiEndpoint}?action=majors&group_id=${groupId}`);
            const data = await response.json();
            
            if (data.success) {
                this.cache.majors[cacheKey] = data.data;
                this.populateSelect(this.elements.major, data.data, 'code', 'name', '-- Chọn ngành học --');
            }
        } catch (error) {
            console.error('Error loading majors:', error);
        }
    },
    
    /**
     * Load document types from API
     */
    loadDocTypes: async function(level) {
        const cacheKey = level;
        
        if (this.cache.docTypes[cacheKey]) {
            this.populateSelect(this.elements.docType, this.cache.docTypes[cacheKey], 'code', 'name', '-- Chọn loại tài liệu --');
            return;
        }
        
        try {
            const response = await fetch(`${this.apiEndpoint}?action=doc_types&level=${level}`);
            const data = await response.json();
            
            if (data.success) {
                this.cache.docTypes[cacheKey] = data.data;
                this.populateSelect(this.elements.docType, data.data, 'code', 'name', '-- Chọn loại tài liệu --');
            }
        } catch (error) {
            console.error('Error loading doc types:', error);
        }
    },
    
    /**
     * Populate a select element with options
     */
    populateSelect: function(elementId, data, valueKey, textKey, placeholder) {
        const select = document.getElementById(elementId);
        if (!select) return;
        
        // Clear existing options
        select.innerHTML = '';
        
        // Add placeholder
        const placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = placeholder;
        select.appendChild(placeholderOption);
        
        // Add options
        data.forEach(item => {
            const option = document.createElement('option');
            option.value = item[valueKey];
            option.textContent = item[textKey];
            select.appendChild(option);
        });
    },
    
    /**
     * Show a container element
     */
    showContainer: function(containerId) {
        const container = document.getElementById(containerId);
        if (container) {
            container.style.display = '';
            container.classList.remove('hidden');
        }
    },
    
    /**
     * Hide a container element
     */
    hideContainer: function(containerId) {
        const container = document.getElementById(containerId);
        if (container) {
            container.style.display = 'none';
            container.classList.add('hidden');
        }
    },
    
    /**
     * Hide all cascade containers
     */
    hideAllContainers: function() {
        this.hideContainer(this.elements.gradeContainer);
        this.hideContainer(this.elements.subjectContainer);
        this.hideContainer(this.elements.majorGroupContainer);
        this.hideContainer(this.elements.majorContainer);
        this.hideContainer(this.elements.docTypeContainer);
    },
    
    /**
     * Reset subsequent selections when a parent selection changes
     */
    resetSubsequentSelections: function(changedField) {
        const order = ['educationLevel', 'grade', 'subject', 'majorGroup', 'major', 'docType'];
        const changedIndex = order.indexOf(changedField);
        
        for (let i = changedIndex + 1; i < order.length; i++) {
            const field = order[i];
            this.state[field === 'grade' ? 'gradeId' : field + (field === 'majorGroup' ? 'Id' : 'Code')] = null;
            
            // Reset the select element
            const elementKey = field === 'grade' ? 'grade' : 
                               field === 'majorGroup' ? 'majorGroup' :
                               field === 'docType' ? 'docType' : field;
            const select = document.getElementById(this.elements[elementKey]);
            if (select) {
                select.value = '';
            }
        }
    },
    
    /**
     * Load cascade from pre-populated state (for edit mode)
     */
    loadCascadeFromState: async function() {
        const eduSelect = document.getElementById(this.elements.educationLevel);
        if (eduSelect && this.state.educationLevel) {
            eduSelect.value = this.state.educationLevel;
            await this.onEducationLevelChange(this.state.educationLevel);
        }
        
        const isPhoThong = ['tieu_hoc', 'thcs', 'thpt'].includes(this.state.educationLevel);
        
        if (isPhoThong) {
            if (this.state.gradeId) {
                const gradeSelect = document.getElementById(this.elements.grade);
                if (gradeSelect) {
                    gradeSelect.value = this.state.gradeId;
                    await this.onGradeChange(this.state.gradeId);
                }
            }
            
            if (this.state.subjectCode) {
                const subjectSelect = document.getElementById(this.elements.subject);
                if (subjectSelect) {
                    subjectSelect.value = this.state.subjectCode;
                }
            }
        } else {
            if (this.state.majorGroupId) {
                const majorGroupSelect = document.getElementById(this.elements.majorGroup);
                if (majorGroupSelect) {
                    majorGroupSelect.value = this.state.majorGroupId;
                    await this.onMajorGroupChange(this.state.majorGroupId);
                }
            }
            
            if (this.state.majorCode) {
                const majorSelect = document.getElementById(this.elements.major);
                if (majorSelect) {
                    majorSelect.value = this.state.majorCode;
                }
            }
        }
        
        if (this.state.docTypeCode) {
            const docTypeSelect = document.getElementById(this.elements.docType);
            if (docTypeSelect) {
                docTypeSelect.value = this.state.docTypeCode;
            }
        }
    },
    
    /**
     * Get current selection values
     */
    getValues: function() {
        return {
            education_level: document.getElementById(this.elements.educationLevel)?.value || '',
            grade_id: document.getElementById(this.elements.grade)?.value || null,
            subject_code: document.getElementById(this.elements.subject)?.value || null,
            major_group_id: document.getElementById(this.elements.majorGroup)?.value || null,
            major_code: document.getElementById(this.elements.major)?.value || null,
            doc_type_code: document.getElementById(this.elements.docType)?.value || ''
        };
    },
    
    /**
     * Validate the selection
     */
    validate: function() {
        const values = this.getValues();
        const errors = [];
        
        if (!values.education_level) {
            errors.push('Vui lòng chọn cấp học');
        }
        
        const isPhoThong = ['tieu_hoc', 'thcs', 'thpt'].includes(values.education_level);
        
        if (isPhoThong) {
            if (!values.grade_id) {
                errors.push('Vui lòng chọn lớp');
            }
            if (!values.subject_code) {
                errors.push('Vui lòng chọn môn học');
            }
        } else if (values.education_level === 'dai_hoc') {
            if (!values.major_group_id) {
                errors.push('Vui lòng chọn nhóm ngành');
            }
            if (!values.major_code) {
                errors.push('Vui lòng chọn ngành học');
            }
        }
        
        if (!values.doc_type_code) {
            errors.push('Vui lòng chọn loại tài liệu');
        }
        
        return {
            isValid: errors.length === 0,
            errors: errors
        };
    }
};

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CategoriesManager;
}

