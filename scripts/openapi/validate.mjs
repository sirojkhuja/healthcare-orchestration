#!/usr/bin/env node

import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, '..', '..');
const openApiDir = path.join(repoRoot, 'docs', 'api', 'openapi');

main();

function main() {
    const specification = readJson(path.join(openApiDir, 'openapi.json'));
    validateContractConventions(specification);
}

function readJson(file) {
    return JSON.parse(readFileSync(file, 'utf8'));
}

function validateContractConventions(specification) {
    const operationIds = new Map();
    const methods = ['get', 'post', 'put', 'patch', 'delete'];

    for (const [routePath, pathItem] of Object.entries(specification.paths ?? {})) {
        for (const method of methods) {
            const operation = pathItem[method];

            if (!operation) {
                continue;
            }

            assert(typeof operation.summary === 'string' && operation.summary !== '', `${method.toUpperCase()} ${routePath} is missing summary`);
            assert(typeof operation.operationId === 'string' && operation.operationId !== '', `${method.toUpperCase()} ${routePath} is missing operationId`);
            assert(Array.isArray(operation.tags) && operation.tags.length > 0, `${method.toUpperCase()} ${routePath} is missing tags`);
            assert(operation.responses && Object.keys(operation.responses).length > 0, `${method.toUpperCase()} ${routePath} is missing responses`);
            assert(Array.isArray(operation.security), `${method.toUpperCase()} ${routePath} must declare security explicitly`);

            const owner = operationIds.get(operation.operationId);
            assert(!owner, `Duplicate operationId ${operation.operationId} on ${owner} and ${method.toUpperCase()} ${routePath}`);
            operationIds.set(operation.operationId, `${method.toUpperCase()} ${routePath}`);

            for (const [statusCode, response] of Object.entries(operation.responses ?? {})) {
                if (!/^[23]\d{2}$/.test(statusCode)) {
                    continue;
                }

                assert(response.headers?.['X-Request-Id'], `${method.toUpperCase()} ${routePath} ${statusCode} is missing X-Request-Id header`);
                assert(response.headers?.['X-Correlation-Id'], `${method.toUpperCase()} ${routePath} ${statusCode} is missing X-Correlation-Id header`);
            }
        }
    }
}

function assert(condition, message) {
    if (!condition) {
        throw new Error(message);
    }
}
