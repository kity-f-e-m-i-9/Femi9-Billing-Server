FROM mysql:8.0

# Bakes in the safe demo seed (schema + sanitized reference data + synthetic
# demo login/products — no real customer/business data, see db-init/README.md)
# so a fresh container is usable without any separate import step.
# Build context for this Dockerfile is the docker/db-init/ folder itself.
COPY 01-schema.sql 02-reference-data.sql 03-dummy-seed.sql /docker-entrypoint-initdb.d/
