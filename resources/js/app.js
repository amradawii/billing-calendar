import Alpine from 'alpinejs';

Alpine.data('schedule', (scenarios) => ({
    scenarios,
    active: scenarios[0].id,
    form: { ...scenarios[0].params },
    result: null,
    errors: {},
    loading: false,
    failed: false,
    request: 0,

    init() {
        this.select(this.active);
    },

    get scenario() {
        return this.scenarios.find((scenario) => scenario.id === this.active);
    },

    get rows() {
        if (!this.result) {
            return [];
        }

        const reasons = Object.fromEntries(this.result.differences.map((d) => [d.index, d.reason]));

        return this.result.correct.map((correct, i) => ({
            index: i,
            naive: this.result.naive[i],
            correct,
            reason: reasons[i] ?? null,
        }));
    },

    get settings() {
        const f = this.form;
        const unit = { daily: 'day', weekly: 'week', monthly: 'month', yearly: 'year' }[f.frequency];
        const every = Number(f.interval) === 1 ? `every ${unit}` : `every ${f.interval} ${unit}s`;
        const zone = f.scenario ? `${f.zone} (simulated rules)` : f.zone;

        return `Starts ${f.start}, ${every} at ${f.time}, ${zone}, ${f.count} occurrences`;
    },

    select(id) {
        this.active = id;
        this.form = { ...this.scenario.params };
        this.run();
    },

    customize() {
        const { scenario, ...params } = this.form;
        this.active = 'custom';
        this.form = params;
        this.run();
    },

    async run() {
        const request = ++this.request;
        this.loading = true;
        this.failed = false;

        try {
            const query = new URLSearchParams(Object.entries(this.form).filter(([, value]) => value !== ''));
            const response = await fetch(`/api/schedule?${query}`, { headers: { Accept: 'application/json' } });
            const body = await response.json();

            if (request !== this.request) {
                return;
            }

            if (response.status === 422) {
                this.errors = body.errors;
                this.result = null;
            } else if (response.ok) {
                this.errors = {};
                this.result = body;
            } else {
                throw new Error(`HTTP ${response.status}`);
            }
        } catch (error) {
            if (request === this.request) {
                this.failed = true;
                this.result = null;
            }
        } finally {
            if (request === this.request) {
                this.loading = false;
            }
        }
    },

    when(occurrence) {
        return `${occurrence.weekday} ${occurrence.date} ${occurrence.time}`;
    },

    instant(occurrence) {
        return `UTC${occurrence.offset}, ${occurrence.utc.slice(11, 16)} UTC`;
    },
}));

window.Alpine = Alpine;

Alpine.start();
