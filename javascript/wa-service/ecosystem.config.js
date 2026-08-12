module.exports = {
    apps: [{
        name: 'wa-service',
        cwd: __dirname,
        script: 'src/index.js',
        instances: 1,
        exec_mode: 'fork',
        autorestart: true,
        watch: false,
        max_memory_restart: '4G',
        restart_delay: 5000,
        exp_backoff_restart_delay: 1000,
        env: {
            NODE_ENV: 'production'
        },
        error_file: 'logs/pm2/error.log',
        out_file: 'logs/pm2/out.log',
        merge_logs: true,
        time: true
    }]
};
