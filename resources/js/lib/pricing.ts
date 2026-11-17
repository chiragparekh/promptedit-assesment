/**
 * Format an exact USD decimal string for display.
 *
 * The amount is calculated on the server; this only shortens it. Small amounts keep
 * four decimals so fractions of a cent stay visible instead of collapsing to "$0.00".
 */
export function formatUsd(amount: string): string {
    const dollars = Number(amount);

    return `$${dollars.toFixed(dollars >= 1 ? 2 : 4)}`;
}
