import { apiRequest, getBasePath } from '../shared/api.js';
import type { AuthStore } from '../shared/authStore.js';
import type { ToastStore } from '../shared/toast.js';
import type { User, UserRole } from '../types/index.js';

interface LoginResponse {
  message: string;
  user: User;
}

interface RegisterResponse {
  message: string;
  user: User;
  email_sent: boolean;
  debug_verify_url?: string;
}

interface ResendResponse {
  message: string;
  email_sent?: boolean;
  debug_verify_url?: string;
}

function registerAuthComponents(): void {
  const Alpine = window.Alpine;
  if (!Alpine) return;

  Alpine.data('loginForm', () => ({
    email: '',
    password: '',
    submitting: false,
    error: '',
    fieldErrors: {} as Record<string, string>,
    showResend: false,
    resending: false,
    resendMessage: '',
    debugVerifyUrl: '',

    async submit(): Promise<void> {
      this.submitting = true;
      this.error = '';
      this.fieldErrors = {};
      this.showResend = false;
      this.resendMessage = '';
      this.debugVerifyUrl = '';

      try {
        const res = await apiRequest<LoginResponse>('/auth/login', {
          method: 'POST',
          body: JSON.stringify({
            email: this.email,
            password: this.password,
          }),
        });

        if (!res.success || !res.data) {
          this.error = res.message ?? 'Unable to sign in.';
          this.fieldErrors = res.errors ?? {};
          if (res.errors?.code === 'EMAIL_NOT_VERIFIED') {
            this.showResend = true;
          }
          return;
        }

        const authStore = Alpine.store('auth') as AuthStore;
        authStore.user = res.data.user;
        const toast = Alpine.store('toast') as ToastStore;
        toast.push('Welcome back, ' + res.data.user.contact_name + '!', 'success');

        const base = getBasePath();
        if (res.data.user.role === 'supplier') {
          window.location.href = `${base}/supplier`;
        } else {
          window.location.href = `${base}/`;
        }
      } catch {
        this.error = 'Network error. Please try again.';
      } finally {
        this.submitting = false;
      }
    },

    async resend(): Promise<void> {
      if (!this.email) return;
      this.resending = true;
      this.resendMessage = '';
      this.debugVerifyUrl = '';
      try {
        const res = await apiRequest<ResendResponse>('/auth/resend-verification', {
          method: 'POST',
          body: JSON.stringify({ email: this.email }),
        });
        if (res.success && res.data) {
          this.resendMessage = res.data.message;
          if (res.data.debug_verify_url) {
            this.debugVerifyUrl = res.data.debug_verify_url;
          }
        } else {
          this.error = res.message ?? 'Could not resend verification email.';
        }
      } catch {
        this.error = 'Network error while resending verification email.';
      } finally {
        this.resending = false;
      }
    },
  }));

  Alpine.data('registerForm', () => ({
    role: 'customer' as Extract<UserRole, 'customer' | 'company'>,
    company_name: '',
    contact_name: '',
    email: '',
    phone: '',
    location: '',
    password: '',
    confirm_password: '',
    submitting: false,
    error: '',
    fieldErrors: {} as Record<string, string>,
    successMessage: '',
    debugVerifyUrl: '',

    async submit(): Promise<void> {
      this.error = '';
      this.fieldErrors = {};
      this.successMessage = '';
      this.debugVerifyUrl = '';

      if (this.password !== this.confirm_password) {
        this.fieldErrors.confirm_password = 'Passwords do not match.';
        return;
      }

      this.submitting = true;
      try {
        const res = await apiRequest<RegisterResponse>('/auth/register', {
          method: 'POST',
          body: JSON.stringify({
            role: this.role,
            company_name: this.role === 'company' ? this.company_name : '',
            contact_name: this.contact_name,
            email: this.email,
            phone: this.phone,
            location: this.location,
            password: this.password,
          }),
        });

        if (!res.success || !res.data) {
          this.error = res.message ?? 'Registration failed.';
          this.fieldErrors = res.errors ?? {};
          return;
        }

        this.successMessage = res.data.message;
        if (res.data.debug_verify_url) {
          this.debugVerifyUrl = res.data.debug_verify_url;
        }
        const toast = Alpine.store('toast') as ToastStore;
        toast.push('Account created! Please verify your email.', 'success');
      } catch {
        this.error = 'Network error. Please try again.';
      } finally {
        this.submitting = false;
      }
    },
  }));

  Alpine.data('verifyResendForm', () => ({
    email: '',
    submitting: false,
    message: '',
    error: '',
    debugVerifyUrl: '',

    async submit(): Promise<void> {
      this.submitting = true;
      this.message = '';
      this.error = '';
      this.debugVerifyUrl = '';
      try {
        const res = await apiRequest<ResendResponse>('/auth/resend-verification', {
          method: 'POST',
          body: JSON.stringify({ email: this.email }),
        });
        if (res.success && res.data) {
          this.message = res.data.message;
          if (res.data.debug_verify_url) {
            this.debugVerifyUrl = res.data.debug_verify_url;
          }
        } else {
          this.error = res.message ?? 'Could not resend verification link.';
        }
      } catch {
        this.error = 'Network error. Please try again.';
      } finally {
        this.submitting = false;
      }
    },
  }));
}

if (document.readyState === 'loading') {
  document.addEventListener('alpine:init', registerAuthComponents);
} else {
  registerAuthComponents();
}
