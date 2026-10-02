import { describe, it, expect, beforeEach } from 'vitest';
import cartReducer, {
  addToCart,
  updateQuantity,
  removeFromCart,
  clearCart,
  type CartItem,
} from './cartSlice';

const sampleItem: CartItem = {
  product_id: 1,
  name: 'Test Product',
  price: 9.99,
  quantity: 2,
};

const anotherItem: CartItem = {
  product_id: 2,
  name: 'Another Product',
  price: 19.99,
  quantity: 1,
};

describe('cartSlice', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('addToCart adds a new item to the cart', () => {
    const state = cartReducer(undefined, addToCart(sampleItem));
    expect(state.items).toHaveLength(1);
    expect(state.items[0]).toEqual(sampleItem);
    // localStorage is updated
    expect(JSON.parse(localStorage.getItem('cart') || '[]')).toEqual([sampleItem]);
  });

  it('addToCart increments quantity for an existing item', () => {
    let state = cartReducer(undefined, addToCart(sampleItem));
    state = cartReducer(state, addToCart({ ...sampleItem, quantity: 3 }));
    expect(state.items).toHaveLength(1);
    expect(state.items[0].quantity).toBe(5); // 2 + 3
  });

  it('updateQuantity changes the quantity of an existing item', () => {
    let state = cartReducer(undefined, addToCart(sampleItem));
    state = cartReducer(state, updateQuantity({ product_id: 1, quantity: 7 }));
    expect(state.items[0].quantity).toBe(7);
  });

  it('updateQuantity is a no-op when item does not exist', () => {
    let state = cartReducer(undefined, addToCart(sampleItem));
    state = cartReducer(state, updateQuantity({ product_id: 999, quantity: 7 }));
    expect(state.items).toHaveLength(1);
    expect(state.items[0].quantity).toBe(2);
  });

  it('removeFromCart removes the item with the given product_id', () => {
    let state = cartReducer(undefined, addToCart(sampleItem));
    state = cartReducer(state, addToCart(anotherItem));
    state = cartReducer(state, removeFromCart(1));
    expect(state.items).toHaveLength(1);
    expect(state.items[0].product_id).toBe(2);
  });

  it('clearCart empties the cart and removes the localStorage entry', () => {
    let state = cartReducer(undefined, addToCart(sampleItem));
    state = cartReducer(state, addToCart(anotherItem));
    expect(state.items).toHaveLength(2);
    state = cartReducer(state, clearCart());
    expect(state.items).toHaveLength(0);
    expect(localStorage.getItem('cart')).toBeNull();
  });
});
