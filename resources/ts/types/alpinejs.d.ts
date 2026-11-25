declare module 'alpinejs' {
  export interface AlpineStatic {
    start(): void;
    store<T = unknown>(name: string, value?: T): T;
    data<T extends object>(name: string, callback: (...args: unknown[]) => T): void;
    nextTick(callback?: () => void): Promise<void>;
    reactive<T extends object>(obj: T): T;
  }

  const Alpine: AlpineStatic;
  export default Alpine;
}
