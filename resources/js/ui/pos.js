/**
 * POS screen helpers (Restaurant, ARCHITECTURE §10.1). The server checks everything again; these only
 * make the touch screen pleasant.
 */

/** The lock screen's PIN pad: pick a person, type the PIN, send it. */
export function pinPad({ userId }) {
    return {
        userId: userId ? Number(userId) : null,
        pin: '',

        choose(id) {
            this.userId = id;
            this.pin = '';
        },

        press(digit) {
            if (this.pin.length < 6) {
                this.pin += digit;
            }
        },
    };
}

/**
 * Closing a POS session: shows the counted cash and difference as notes are entered, and when the
 * difference is larger than the limit asks a manager for their PIN before sending the form.
 */
export function managerApproval({ url, action, subjectId, expected, limit, values, why }) {
    return {
        counts: {},
        asking: false,
        managerId: null,
        pin: '',
        busy: false,
        error: '',
        why,

        get counted() {
            return values.reduce((sum, value) => sum + Number(value) * (Number(this.counts[value]) || 0), 0);
        },

        get variance() {
            return Math.round((this.counted - expected) * 100) / 100;
        },

        check(event) {
            if (Math.abs(this.variance) > limit && !this.$refs.approval.value) {
                event.preventDefault();
                this.asking = true;
            }
        },

        async approve() {
            this.busy = true;
            this.error = '';

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ action, manager_id: this.managerId, pin: this.pin, subject_id: subjectId }),
                });
                const data = await response.json().catch(() => ({}));

                if (response.ok && data.ok) {
                    this.$refs.approval.value = data.approval_id;
                    this.asking = false;
                    this.$root.submit();

                    return;
                }

                this.error = data.message || Object.values(data.errors ?? {}).flat()[0] || response.statusText;
            } catch {
                this.error = 'No connection. Try again.';
            } finally {
                this.pin = '';
                this.busy = false;
            }
        },
    };
}

/** Locks the terminal (signs the person out) after some minutes without a touch or key. */
export function posIdle(minutes) {
    return {
        timer: null,

        init() {
            if (!minutes) {
                return;
            }

            const reset = () => {
                clearTimeout(this.timer);
                this.timer = setTimeout(() => document.querySelector('[data-lock-form]')?.submit(), minutes * 60 * 1000);
            };

            ['pointerdown', 'keydown', 'scroll'].forEach((type) => window.addEventListener(type, reset, { passive: true }));
            reset();
        },
    };
}
