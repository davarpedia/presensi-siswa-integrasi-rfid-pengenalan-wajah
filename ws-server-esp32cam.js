const WebSocket = require('ws');
const wss = new WebSocket.Server({ host: '0.0.0.0', port: 3001 });

wss.on('connection', (ws) => {
    console.log("ESP32-CAM connected");

    ws.on('message', (message) => {
        if (message instanceof Buffer) {
            console.log('Received image data');
            // Kirimkan gambar ke semua klien
            wss.clients.forEach(client => {
                if (client !== ws && client.readyState === WebSocket.OPEN) {
                    client.send(message);
                }
            });
        } else {
            console.log('Received non-image data:', message);
        }
    });

    ws.on('close', () => {
        console.log('ESP32-CAM disconnected');
    });
});

console.log('WebSocket server running on ws://0.0.0.0:3001');  