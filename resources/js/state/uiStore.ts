import { create } from 'zustand';

interface UiState {
    commandPaletteOpen: boolean;
    setCommandPaletteOpen: (open: boolean) => void;

    /** Currently selected responder id (links roster ↔ map). */
    selectedResponderId: string | null;
    setSelectedResponderId: (id: string | null) => void;

    /** Mobile nav drawer (collapsed rail). */
    navDrawerOpen: boolean;
    setNavDrawerOpen: (open: boolean) => void;
}

export const useUiStore = create<UiState>((set) => ({
    commandPaletteOpen: false,
    setCommandPaletteOpen: (open) => set({ commandPaletteOpen: open }),

    selectedResponderId: null,
    setSelectedResponderId: (id) => set({ selectedResponderId: id }),

    navDrawerOpen: false,
    setNavDrawerOpen: (open) => set({ navDrawerOpen: open }),
}));
