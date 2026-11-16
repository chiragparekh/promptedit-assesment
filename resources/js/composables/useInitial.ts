export type UseInitialReturn = {
    getInitial: (fullName?: string) => string;
};

function firstLetter(name: string): string {
    return Array.from(name)[0] ?? '';
}

export function getInitial(fullName?: string): string {
    const name = fullName?.trim();

    if (!name) {
        return '';
    }

    return firstLetter(name).toUpperCase();
}

export function useInitial(): UseInitialReturn {
    return { getInitial };
}
