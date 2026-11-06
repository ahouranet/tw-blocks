# Dockerfile for TW Blocks Remote Build API (Driver B)
FROM node:22-alpine

WORKDIR /app

# Create non-root user for security
RUN addgroup -S twb && adduser -S twb -G twb

COPY package.json ./
RUN npm install --omit=dev && npm cache clean --force

COPY server.js ./

USER twb

ENV NODE_ENV=production
ENV PORT=4000

EXPOSE 4000

CMD ["node", "server.js"]
