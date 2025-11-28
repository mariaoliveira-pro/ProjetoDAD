export const handleConnectionEvents = (io, socket) => {
  socket.on("echo", (msg) => {
    socket.emit("echo", msg);
  });
};
