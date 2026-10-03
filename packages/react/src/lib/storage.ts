/** localStorage that never throws: private windows and blocked storage just don't remember. */
export const storage = {
    get(key: string): string | null {
        try {
            return localStorage.getItem(key);
        } catch {
            return null;
        }
    },
    /** Store a value, or remove the key with null. */
    set(key: string, value: string | null): void {
        try {
            value === null ? localStorage.removeItem(key) : localStorage.setItem(key, value);
        } catch {
            // The value still applies for this page; it just isn't remembered.
        }
    },
};
