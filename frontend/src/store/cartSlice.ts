import { createSlice, PayloadAction } from '@reduxjs/toolkit';

export interface CartItem {
  product_id: number;
  name: string;
  price: number;
  quantity: number;
  payload?: string[] | Record<string, unknown>;
  product_type?: 'auto' | 'manual';
}

interface CartState {
  items: CartItem[];
}

const stored = (() => {
  try {
    const raw = localStorage.getItem('cart');
    return raw ? (JSON.parse(raw) as CartItem[]) : [];
  } catch {
    return [];
  }
})();

const initialState: CartState = { items: stored };

const cartSlice = createSlice({
  name: 'cart',
  initialState,
  reducers: {
    addToCart(state, action: PayloadAction<CartItem>) {
      const idx = state.items.findIndex((i) => i.product_id === action.payload.product_id);
      if (idx >= 0) {
        state.items[idx].quantity += action.payload.quantity;
      } else {
        state.items.push(action.payload);
      }
      localStorage.setItem('cart', JSON.stringify(state.items));
    },
    updateQuantity(state, action: PayloadAction<{ product_id: number; quantity: number }>) {
      const item = state.items.find((i) => i.product_id === action.payload.product_id);
      if (item) {
        item.quantity = action.payload.quantity;
        localStorage.setItem('cart', JSON.stringify(state.items));
      }
    },
    removeFromCart(state, action: PayloadAction<number>) {
      state.items = state.items.filter((i) => i.product_id !== action.payload);
      localStorage.setItem('cart', JSON.stringify(state.items));
    },
    clearCart(state) {
      state.items = [];
      localStorage.removeItem('cart');
    },
  },
});

export const { addToCart, updateQuantity, removeFromCart, clearCart } = cartSlice.actions;
export default cartSlice.reducer;