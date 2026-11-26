import type { ColorScheme, ThemeDefinition, ThemeModeId } from '../types/index.js';

export const THEME_STORAGE_KEY = 'fresh_produce_theme_preference';
export const DEFAULT_THEME_MODE: ThemeModeId = 'light';

const LIGHT_THEME_DEFINITION: ThemeDefinition = {
  id: 'light',
  label: 'Light Mode',
  shortLabel: 'Light',
  description: 'Bright high-contrast storefront palette',
  colorScheme: 'light',
  icon: 'sun',
};

const BUILTIN_THEMES: ThemeDefinition[] = [
  LIGHT_THEME_DEFINITION,
  {
    id: 'dark',
    label: 'Dark Mode',
    shortLabel: 'Dark',
    description: 'Low-glare dark stone and emerald palette',
    colorScheme: 'dark',
    icon: 'moon',
  },
  {
    id: 'system',
    label: 'System Theme',
    shortLabel: 'Auto',
    description: 'Automatically follows your device appearance',
    colorScheme: 'system',
    icon: 'monitor',
  },
];

const registeredThemes: ThemeDefinition[] = [...BUILTIN_THEMES];

export function getRegisteredThemes(): ThemeDefinition[] {
  return [...registeredThemes];
}

export function registerTheme(definition: ThemeDefinition): void {
  const existingIndex = registeredThemes.findIndex((item) => item.id === definition.id);
  if (existingIndex >= 0) {
    registeredThemes[existingIndex] = definition;
  } else {
    registeredThemes.push(definition);
  }
}

function findThemeDefinition(id: ThemeModeId): ThemeDefinition {
  return (
    registeredThemes.find((item) => item.id === id) ??
    registeredThemes[0] ??
    LIGHT_THEME_DEFINITION
  );
}

function getSystemColorScheme(): ColorScheme {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
    return 'light';
  }
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function resolveTheme(preference: ThemeModeId): { resolved: ThemeModeId; colorScheme: ColorScheme } {
  const def = findThemeDefinition(preference);
  if (def.colorScheme === 'system') {
    const scheme = getSystemColorScheme();
    return { resolved: scheme, colorScheme: scheme };
  }
  return { resolved: def.id, colorScheme: def.colorScheme };
}

function readStoredPreference(): ThemeModeId {
  try {
    const stored = window.localStorage.getItem(THEME_STORAGE_KEY);
    if (stored && registeredThemes.some((item) => item.id === stored)) {
      return stored;
    }
  } catch {
    // Ignore storage read errors in restricted browsing contexts
  }
  return DEFAULT_THEME_MODE;
}

function persistPreference(mode: ThemeModeId): void {
  try {
    window.localStorage.setItem(THEME_STORAGE_KEY, mode);
  } catch {
    // Ignore storage write errors in restricted browsing contexts
  }
}

function applyThemeToDocument(
  preference: ThemeModeId,
  resolved: ThemeModeId,
  colorScheme: ColorScheme
): void {
  if (typeof document === 'undefined') {
    return;
  }
  const root = document.documentElement;
  const isDark = colorScheme === 'dark';
  root.dataset.theme = resolved;
  root.dataset.themePreference = preference;
  root.classList.toggle('dark', isDark);
  root.style.colorScheme = colorScheme;
}

export interface ThemeStore {
  preference: ThemeModeId;
  resolved: ThemeModeId;
  isDark: boolean;
  modes: ThemeDefinition[];
  init(): void;
  setPreference(mode: ThemeModeId): void;
  cycle(): void;
  toggleLightDark(): void;
  registerMode(definition: ThemeDefinition): void;
  currentDefinition(): ThemeDefinition;
  nextDefinition(): ThemeDefinition;
}

export function createThemeStore(): ThemeStore {
  let mediaQuery: MediaQueryList | null = null;
  let initialized = false;

  return {
    preference: DEFAULT_THEME_MODE,
    resolved: DEFAULT_THEME_MODE,
    isDark: false,
    modes: getRegisteredThemes(),

    init(): void {
      if (initialized) {
        return;
      }
      initialized = true;

      this.modes = getRegisteredThemes();
      const stored = readStoredPreference();
      const { resolved, colorScheme } = resolveTheme(stored);
      this.preference = stored;
      this.resolved = resolved;
      this.isDark = colorScheme === 'dark';
      applyThemeToDocument(this.preference, this.resolved, colorScheme);

      if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
        mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const onSystemChange = (): void => {
          const def = findThemeDefinition(this.preference);
          if (def.colorScheme !== 'system') {
            return;
          }
          const next = resolveTheme(this.preference);
          this.resolved = next.resolved;
          this.isDark = next.colorScheme === 'dark';
          applyThemeToDocument(this.preference, this.resolved, next.colorScheme);
        };

        if (typeof mediaQuery.addEventListener === 'function') {
          mediaQuery.addEventListener('change', onSystemChange);
        } else if (typeof mediaQuery.addListener === 'function') {
          mediaQuery.addListener(onSystemChange);
        }
      }

      if (typeof window !== 'undefined') {
        window.addEventListener('storage', (event: StorageEvent) => {
          if (event.key !== THEME_STORAGE_KEY) {
            return;
          }
          const synced = readStoredPreference();
          const next = resolveTheme(synced);
          this.preference = synced;
          this.resolved = next.resolved;
          this.isDark = next.colorScheme === 'dark';
          applyThemeToDocument(this.preference, this.resolved, next.colorScheme);
        });
      }
    },

    setPreference(mode: ThemeModeId): void {
      const def = findThemeDefinition(mode);
      const { resolved, colorScheme } = resolveTheme(def.id);
      this.preference = def.id;
      this.resolved = resolved;
      this.isDark = colorScheme === 'dark';
      persistPreference(def.id);
      applyThemeToDocument(this.preference, this.resolved, colorScheme);

      if (typeof window !== 'undefined') {
        window.dispatchEvent(
          new CustomEvent('theme:changed', {
            detail: {
              preference: this.preference,
              resolved: this.resolved,
              colorScheme,
            },
          })
        );
      }
    },

    cycle(): void {
      const next = this.nextDefinition();
      this.setPreference(next.id);
    },

    toggleLightDark(): void {
      this.setPreference(this.isDark ? 'light' : 'dark');
    },

    registerMode(definition: ThemeDefinition): void {
      registerTheme(definition);
      this.modes = getRegisteredThemes();
    },

    currentDefinition(): ThemeDefinition {
      return findThemeDefinition(this.preference);
    },

    nextDefinition(): ThemeDefinition {
      const list = this.modes.length > 0 ? this.modes : getRegisteredThemes();
      const idx = list.findIndex((item) => item.id === this.preference);
      const nextIdx = idx >= 0 ? (idx + 1) % list.length : 0;
      return list[nextIdx] ?? LIGHT_THEME_DEFINITION;
    },
  };
}
