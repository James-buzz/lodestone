import { useEffect, useState } from 'react';
import { storage } from './storage';

/** The theme choice; 'system' follows prefers-color-scheme. */
export type Theme = 'light' | 'dark' | 'system';

const media = () => window.matchMedia('(prefers-color-scheme: dark)');

function apply(theme: Theme) {
    document.documentElement.classList.toggle('dark', theme === 'dark' || (theme === 'system' && media().matches));
}

/** The theme, stored in localStorage and applied as the `.dark` class shadcn expects. */
export function useTheme(): [Theme, (theme: Theme) => void] {
    const [theme, setTheme] = useState<Theme>(() => (storage.get('theme') as Theme | null) ?? 'system');

    useEffect(() => {
        storage.set('theme', theme === 'system' ? null : theme);
        apply(theme);

        if (theme !== 'system') return;
        const mq = media();
        const onChange = () => apply('system');
        mq.addEventListener('change', onChange);
        return () => mq.removeEventListener('change', onChange);
    }, [theme]);

    return [theme, setTheme];
}
