# =========================
# Base
# =========================
FROM node:25-alpine3.23 AS base
WORKDIR /app
COPY package*.json ./

# =========================
# Dev
# =========================
FROM base AS dev
RUN npm install
COPY . .
CMD ["npm", "run", "dev", "--", "--host"]

# =========================
# Prod
# =========================
FROM base AS build
RUN npm install
COPY . .
RUN npm run build

FROM nginx:alpine AS prod
COPY --from=build /app/dist /usr/share/nginx/html
