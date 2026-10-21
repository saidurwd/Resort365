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
            const options = { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, ...(config.options || {}) };

            // Scatter points carry the name of what they show (a dish), which the tooltip prints.
            if (config.pointLabels) {
                options.interaction = { mode: 'nearest', intersect: true };
                options.plugins = {
                    ...(options.plugins || {}),
                    tooltip: { callbacks: { label: (context) => (context.raw && context.raw.name ? `${context.raw.name} (${context.raw.x}%, ${context.raw.y})` : context.formattedValue) } },
                };
            }

            this.instance = new Chart(this.$refs.canvas, { ...config, options });
        },
        destroy() {
            this.instance?.destroy();
        },
    };
}
