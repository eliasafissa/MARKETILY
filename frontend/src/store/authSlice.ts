import { createSlice, PayloadAction } from '@reduxjs/toolkit';

export interface User {
  id: number;
  name: string;
  email: string;
  vip_level: string;
  balance: string;
  banned_at: string | null;
  roles?: Array<{ id: number; name: string }>;
}

interface AuthState {
  user: User | null;
  isAuthenticated: boolean;
}

const stored = (() => {
  try {
    const raw = localStorage.getItem('auth_user');
    return raw ? (JSON.parse(raw) as User) : null;
  } catch {
    return null;
  }
})();

const initialState: AuthState = {
  user: stored,
  isAuthenticated: !!stored,
};

const authSlice = createSlice({
  name: 'auth',
  initialState,
  reducers: {
    setUser(state, action: PayloadAction<User>) {
      state.user = action.payload;
      state.isAuthenticated = true;
      localStorage.setItem('auth_user', JSON.stringify(action.payload));
    },
    logout(state) {
      state.user = null;
      state.isAuthenticated = false;
      localStorage.removeItem('auth_user');
      localStorage.removeItem('token');
    },
    updateBalance(state, action: PayloadAction<string>) {
      if (state.user) {
        state.user.balance = action.payload;
        localStorage.setItem('auth_user', JSON.stringify(state.user));
      }
    },
  },
});

export const { setUser, logout, updateBalance } = authSlice.actions;
export default authSlice.reducer;
