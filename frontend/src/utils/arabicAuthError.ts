// MARKETLLY_AUTH_AR_ERRORS_V3
export const arabicAuthError = (error: any, kind: 'login' | 'register'): string => {
  const status = Number(error?.response?.status ?? 0);
  const fields = error?.response?.data?.errors;
  if (status === 401) return 'البريد الإلكتروني أو كلمة المرور غير صحيحة.';
  if (status === 403) return 'هذا الحساب موقوف. يرجى التواصل مع الإدارة.';
  if (status === 429) return 'محاولات كثيرة. انتظر قليلاً ثم حاول مجدداً.';
  if (status === 422 && fields && typeof fields === 'object') {
    const first = Object.keys(fields)[0];
    return arabicFieldError(first, String(fields[first]?.[0] ?? ''));
  }
  if (status === 422) return kind === 'login' ? 'تحقق من بيانات الدخول.' : 'يرجى مراجعة حقول التسجيل.';
  if (!error?.response) return 'تعذر الاتصال بالخادم. تحقق من الاتصال وحاول مجدداً.';
  return kind === 'login' ? 'تعذر تسجيل الدخول. حاول مجدداً.' : 'تعذر إنشاء الحساب. حاول مجدداً.';
};

export const arabicFieldError = (field: string, message = ''): string => {
  const rule = message.toLowerCase();
  const label: Record<string, string> = {
    name: 'الاسم', email: 'البريد الإلكتروني', password: 'كلمة المرور',
    password_confirmation: 'تأكيد كلمة المرور',
  };
  const name = label[field] ?? 'الحقل';
  if (rule.includes('taken') || rule.includes('unique')) return 'البريد الإلكتروني مستخدم مسبقاً.';
  if (rule.includes('confirmed') || field === 'password_confirmation') return 'تأكيد كلمة المرور غير مطابق.';
  if (rule.includes('required')) return `${name} مطلوب.`;
  if (rule.includes('email')) return 'صيغة البريد الإلكتروني غير صحيحة.';
  if (rule.includes('min') || rule.includes('at least')) return field === 'password' ? 'كلمة المرور يجب أن تكون 10 أحرف على الأقل.' : `${name} أقصر من المسموح.`;
  if (rule.includes('max')) return `${name} أطول من المسموح.`;
  if (field === 'password') return 'كلمة المرور لا تحقق شروط الأمان (حرف كبير وصغير ورقم ورمز).';
  return `راجع ${name} وحاول مجدداً.`;
};
