export interface ToastMessage {
  id: number;
  type: 'success' | 'error' | 'info';
  text: string;
}

export interface ToastStore {
  messages: ToastMessage[];
  push(text: string, type?: 'success' | 'error' | 'info'): void;
  dismiss(id: number): void;
}

export function createToastStore(): ToastStore {
  let nextId = 1;

  return {
    messages: [],

    push(text: string, type: 'success' | 'error' | 'info' = 'info'): void {
      const id = nextId++;
      this.messages.push({ id, type, text });
      window.setTimeout(() => {
        this.dismiss(id);
      }, 4500);
    },

    dismiss(id: number): void {
      this.messages = this.messages.filter((m) => m.id !== id);
    },
  };
}
