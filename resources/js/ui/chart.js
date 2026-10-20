import Chart from 'chart.js/auto';

/**
 * A Chart.js chart drawn from a server-built configuration (the dashboard, Step 7.1):
 * <div x-data="chart(@js($config))"><canvas x-ref="canvas"></canvas></div>
 * The configuration carries labels and data only; the server decides every figure.
 */
export function chart(config) {
    return {
        instance: null,
        init() {
            this.instance = new Chart(this.$refs.canvas, {
                ...config,
                options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, ...(config.options || {}) },
            });
        },
        destroy() {
            this.instance?.destroy();
        },
    };
}
