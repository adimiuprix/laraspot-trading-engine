/**
 * Format symbol for display purposes
 * Converts BTCUSDT to BTC/USDT for better readability
 */
export function formatSymbol(symbol: string): string {
    if (symbol.includes('/')) {
        return symbol;
    }

    // Common quote currencies to check
    const quotePatterns = ['USDT', 'USDC', 'BUSD', 'USD', 'BTC', 'ETH', 'BNB'];

    for (const quote of quotePatterns) {
        if (symbol.endsWith(quote)) {
            const base = symbol.slice(0, -quote.length);

            return `${base}/${quote}`;
        }
    }

    // Fallback: assume last 4 characters are quote
    const base = symbol.slice(0, -4);
    const quote = symbol.slice(-4);

    return `${base}/${quote}`;
}

/**
 * Parse symbol into base and quote currencies
 */
export function parseSymbol(symbol: string): { base: string; quote: string } {
    if (symbol.includes('/')) {
        const [base, quote] = symbol.split('/');

        return { base, quote };
    }

    const quotePatterns = ['USDT', 'USDC', 'BUSD', 'USD', 'BTC', 'ETH', 'BNB'];

    for (const quote of quotePatterns) {
        if (symbol.endsWith(quote)) {
            const base = symbol.slice(0, -quote.length);

            return { base, quote };
        }
    }

    // Fallback: assume last 4 characters are quote
    const base = symbol.slice(0, -4);
    const quote = symbol.slice(-4);

    return { base, quote };
}
