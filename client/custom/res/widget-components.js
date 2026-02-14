/**
 * Shared Vue Components for Widgets
 * 
 * Include this file in all widget HTML files to use shared components
 */

/**
 * WidgetSidebar Component
 * 
 * Usage:
 * <widget-sidebar
 *   title="Your Title"
 *   subtitle="Your Subtitle"
 *   description="Your description text"
 *   :current-step="1"
 *   :total-steps="3"
 *   badge-text="Optional Badge"
 * ></widget-sidebar>
 */
const WidgetSidebar = {
    props: {
        title: { type: String, required: true },
        subtitle: { type: String, default: 'SPARK Center Booming' },
        description: { type: String, default: '' },
        currentStep: { type: Number, default: null },
        totalSteps: { type: Number, default: null },
        badgeText: { type: String, default: null },
        features: { type: Array, default: () => [] } // For referral page
    },
    template: `
        <div class="md:w-72 p-8 border-r border-gray-100 flex flex-col bg-white">
            <!-- Logo -->
            <div class="mb-6 p-3 border border-gray-100 inline-block rounded-xl shadow-sm w-fit">
                <img src="?entryPoint=LogoImage" class="h-10 w-auto object-contain">
            </div>
            
            <!-- Subtitle -->
            <h2 class="text-gray-400 text-[10px] font-bold uppercase tracking-[0.2em] mb-2">
                {{ subtitle }}
            </h2>
            
            <!-- Title -->
            <h1 class="text-xl font-black text-gray-900 leading-tight mb-4">
                {{ title }}
            </h1>
            
            <!-- Description -->
            <p v-if="description" class="text-[11px] text-gray-500 leading-relaxed mt-4">
                {{ description }}
            </p>

            <!-- Features (for referral page) -->
            <div v-if="features.length > 0" class="space-y-4 pt-6 border-t border-gray-100 mt-auto">
                <div v-for="feature in features" :key="feature" 
                     class="flex items-center gap-3 text-gray-700 font-bold text-xs">
                    <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <span>{{ feature }}</span>
                </div>
            </div>
            
            <!-- Progress Indicator -->
            <div v-if="currentStep && totalSteps" class="mt-auto">
                <div class="flex gap-1 mb-2">
                    <div v-for="i in totalSteps" :key="i"
                         :class="[currentStep >= i ? 'bg-blue-600' : 'bg-gray-200']"
                         class="h-1 flex-1 rounded-full transition-all">
                    </div>
                </div>
                <p class="text-[10px] font-bold text-gray-400 uppercase">
                    Stap {{ currentStep }} van {{ totalSteps }}
                </p>
            </div>
            
            <!-- Badge (for direct entry) -->
            <div v-if="badgeText" class="mt-auto pt-6 border-t border-gray-100">
                <p class="text-[10px] font-bold text-blue-600 uppercase tracking-widest">
                    {{ badgeText }}
                </p>
            </div>
        </div>
    `
};

/**
 * LoadingOverlay Component
 * 
 * Usage:
 * <loading-overlay v-if="loading" message="Bezig met opslaan..."></loading-overlay>
 */
const LoadingOverlay = {
    props: {
        message: { type: String, default: 'Even geduld...' }
    },
    template: `
        <div class="absolute inset-0 bg-white/80 z-10 flex items-center justify-center backdrop-blur-sm rounded-2xl">
            <div class="flex flex-col items-center">
                <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-600 mb-3"></div>
                <span class="text-xs font-bold text-blue-600 uppercase tracking-widest">
                    {{ message }}
                </span>
            </div>
        </div>
    `
};

/**
 * ErrorAlert Component
 * 
 * Usage:
 * <error-alert v-if="errorMessage" :message="errorMessage"></error-alert>
 */
const ErrorAlert = {
    props: {
        message: { type: String, required: true }
    },
    template: `
        <div class="p-4 bg-red-50 border border-red-100 rounded-xl flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <p class="text-sm text-red-600 font-medium">{{ message }}</p>
        </div>
    `
};

/**
 * SuccessAlert Component
 * 
 * Usage:
 * <success-alert v-if="success" message="Lead succesvol toegevoegd!"></success-alert>
 */
const SuccessAlert = {
    props: {
        message: { type: String, required: true }
    },
    template: `
        <div class="mt-6 p-4 bg-green-50 border border-green-100 rounded-xl flex items-center gap-3 animate-pulse">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path d="M5 13l4 4L19 7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <p class="text-sm text-green-700 font-bold uppercase tracking-tight">{{ message }}</p>
        </div>
    `
};

/**
 * MultiSelectButton Component
 * 
 * Usage:
 * <multi-select-button
 *   v-for="opt in options"
 *   :key="opt"
 *   :label="opt"
 *   :selected="form.field.includes(opt)"
 *   @click="toggleField(opt)"
 * ></multi-select-button>
 */
const MultiSelectButton = {
    props: {
        label: { type: String, required: true },
        selected: { type: Boolean, default: false }
    },
    emits: ['click'],
    template: `
        <button
            type="button"
            @click="$emit('click')"
            :class="[
                selected 
                    ? 'border-blue-600 bg-blue-50 text-blue-600' 
                    : 'border-gray-100 text-gray-600 hover:border-blue-200'
            ]"
            class="text-left p-3 border-2 rounded-xl text-[11px] font-bold transition-all">
            <span v-if="selected" class="mr-1">✓</span>
            {{ label }}
        </button>
    `
};

/**
 * FormInput Component with inline validation
 * 
 * Usage:
 * <form-input
 *   v-model="form.firstName"
 *   placeholder="Voornaam *"
 *   :error="errors.firstName"
 *   @blur="markTouched('firstName')"
 *   required
 * ></form-input>
 */
const FormInput = {
    props: {
        modelValue: { type: String, default: '' },
        placeholder: { type: String, default: '' },
        type: { type: String, default: 'text' },
        required: { type: Boolean, default: false },
        error: { type: String, default: '' }
    },
    emits: ['update:modelValue', 'blur'],
    template: `
        <div>
            <input
                :type="type"
                :placeholder="placeholder"
                :required="required"
                :value="modelValue"
                @input="$emit('update:modelValue', $event.target.value)"
                @blur="$emit('blur')"
                :class="[
                    error 
                        ? 'border-red-300 focus:border-red-500 bg-red-50' 
                        : 'border-gray-100 focus:border-blue-600 bg-gray-50'
                ]"
                class="w-full p-4 border rounded-xl outline-none text-sm font-medium transition-all"
            />
            <p v-if="error" class="mt-1.5 text-xs text-red-600 font-medium flex items-center gap-1">
                {{ error }}
            </p>
        </div>
    `
};

/**
 * SubmitButton Component
 * 
 * Usage:
 * <submit-button
 *   :disabled="!isValid || loading"
 *   :loading="loading"
 *   label="Lead Aanmaken"
 * ></submit-button>
 */
const SubmitButton = {
    props: {
        label: { type: String, required: true },
        disabled: { type: Boolean, default: false },
        loading: { type: Boolean, default: false },
        variant: { type: String, default: 'blue' } // blue or green
    },
    computed: {
        buttonClasses() {
            const base = 'w-full py-4 rounded-xl font-bold shadow-lg transition-all transform active:scale-[0.98]';
            
            if (this.disabled) {
                return `${base} bg-gray-200 cursor-not-allowed opacity-50 text-gray-500`;
            }
            
            return `${base} bg-blue-600 text-white shadow-blue-200 hover:bg-blue-700`;
        }
    },
    template: `
        <button
            type="submit"
            :disabled="disabled"
            :class="buttonClasses">
            {{ loading ? 'Bezig...' : label }}
        </button>
    `
};

/**
 * Composable: useWidgetSubmit
 * 
 * Reusable logic for submitting widget forms with built-in validation flow
 * 
 * @param {Ref} form - The form ref object
 * @param {Function} onSuccess - Callback function on successful submission
 * @param {Object} validation - Optional validation object from useInlineValidation
 * @returns {Object} { loading, success, errorMessage, submitForm }
 */
function useWidgetSubmit(form, onSuccess, validation = null) {
    const loading = Vue.ref(false);
    const success = Vue.ref(false);
    const errorMessage = Vue.ref('');

    const submitForm = async () => {
        if (validation) {
            validation.markAllTouched();

            if (!validation.isFormValid.value) {
                errorMessage.value = 'Vul alle velden correct in.';
                return;
            }
        }

        loading.value = true;
        success.value = false;
        errorMessage.value = '';

        if (form.value.phone && window.FormValidation) {
            form.value.phone = window.FormValidation.formatBelgianPhone(form.value.phone);
        }

        try {
            const res = await fetch('/api/v1/widget/submit', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(form.value)
            });

            if (res.ok) {
                success.value = true;
                
                if (validation && validation.touched) {
                    Object.keys(validation.touched.value).forEach(key => {
                        validation.touched.value[key] = false;
                    });
                }
                
                if (onSuccess) {
                    onSuccess();
                }
            } else {
                const data = await res.json();
                errorMessage.value = data.error || 'Er is iets misgegaan.';
            }
        } catch (e) {
            errorMessage.value = 'Netwerkfout. Probeer het opnieuw.';
        } finally {
            loading.value = false;
        }
    };

    return {
        loading,
        success,
        errorMessage,
        submitForm
    };
}

/**
 * Composable: useFieldValidation
 * 
 * Enhanced validation with declarative field rules
 * 
 * Usage:
 * const { errors, markTouched, isFormValid, fields } = useFieldValidation({
 *   firstName: { 
 *     value: () => form.value.firstName,
 *     rules: [ValidationRules.required(), ValidationRules.name('Voornaam')]
 *   },
 *   email: {
 *     value: () => form.value.email,
 *     rules: [ValidationRules.required(), ValidationRules.email()]
 *   },
 *   interests: {
 *     value: () => form.value.interests,
 *     rules: [ValidationRules.minItems(1, 'Selecteer ten minste één interesse')]
 *   }
 * });
 */
function useFieldValidation(fieldConfigs) {
    const touched = Vue.ref({});
    
    // Initialize touched state for all fields
    Object.keys(fieldConfigs).forEach(fieldName => {
        touched.value[fieldName] = false;
    });

    const errors = Vue.computed(() => {
        const errs = {};

        Object.entries(fieldConfigs).forEach(([fieldName, config]) => {
            errs[fieldName] = '';

            // Only show errors for touched fields
            if (!touched.value[fieldName]) {
                return;
            }

            // Get current value
            const value = typeof config.value === 'function' 
                ? config.value() 
                : config.value;

            // Validate with rules
            if (config.rules && window.validateWithRules) {
                const result = window.validateWithRules(value, config.rules);
                if (!result.isValid) {
                    errs[fieldName] = result.error;
                }
            }
        });

        return errs;
    });

    const markTouched = (field) => {
        touched.value[field] = true;
    };

    const markAllTouched = () => {
        Object.keys(touched.value).forEach(key => {
            touched.value[key] = true;
        });
    };

    const isFormValid = Vue.computed(() => {
        // Check all fields regardless of touched state
        for (const [fieldName, config] of Object.entries(fieldConfigs)) {
            const value = typeof config.value === 'function' 
                ? config.value() 
                : config.value;

            if (config.rules && window.validateWithRules) {
                const result = window.validateWithRules(value, config.rules);
                if (!result.isValid) {
                    return false;
                }
            }
        }
        return true;
    });

    // Return field names for easy access
    const fields = Object.keys(fieldConfigs);

    return {
        errors,
        touched,
        markTouched,
        markAllTouched,
        isFormValid,
        fields
    };
}

// Export all components
if (typeof window !== 'undefined') {
    window.WidgetComponents = {
        WidgetSidebar,
        LoadingOverlay,
        ErrorAlert,
        SuccessAlert,
        MultiSelectButton,
        FormInput,
        SubmitButton,
        useWidgetSubmit,
        useFieldValidation
    };
}