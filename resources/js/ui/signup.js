/**
 * Sign-up (Step 7.4): checks as the person types whether the subdomain they want is free.
 * The server decides again when the form is sent.
 */
export function subdomainCheck(url) {
    return {
        slug: '',
        message: '',
        available: false,
        init() {
            this.slug = this.$root.querySelector('input[name="slug"]')?.value ?? '';
        },
        async check() {
            const slug = this.slug.trim().toLowerCase();

            if (slug.length < 3) {
                this.message = '';

                return;
            }

            try {
                const response = await fetch(`${url}?slug=${encodeURIComponent(slug)}`, { headers: { Accept: 'application/json' } });
                const result = await response.json();
                this.available = result.available;
                this.message = result.message;
            } catch {
                this.message = '';
            }
        },
    };
}
