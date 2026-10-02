import { createSlice, PayloadAction } from '@reduxjs/toolkit';

type Language = 'en' | 'ar';

interface LanguageState {
  locale: Language;
  dir: 'ltr' | 'rtl';
}

// Locked to Arabic — no language switching
const stored = 'ar' as Language;

// Apply dir/lang on initial load
if (typeof document !== 'undefined') {
  document.documentElement.dir = 'rtl';
  document.documentElement.lang = 'ar';
}

const initialState: LanguageState = {
  locale: 'ar',
  dir: 'rtl',
};

const languageSlice = createSlice({
  name: 'language',
  initialState,
  reducers: {
    // Kept for compatibility but does nothing — Arabic only
    setLanguage(state, action: PayloadAction<Language>) {
      state.locale = 'ar';
      state.dir = 'rtl';
      localStorage.setItem('language', 'ar');
      document.documentElement.dir = 'rtl';
      document.documentElement.lang = 'ar';
    },
  },
});

export const { setLanguage } = languageSlice.actions;
export default languageSlice.reducer;
