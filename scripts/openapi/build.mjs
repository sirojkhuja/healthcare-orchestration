#!/usr/bin/env node

import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import YAML from 'yaml';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, '..', '..');
const openApiDir = path.join(repoRoot, 'docs', 'api', 'openapi');

const fragments = [
    { key: 'identity_access_auth', file: 'identity-access-auth.yaml' },
    { key: 'patients_providers', file: 'patients-providers.yaml' },
    { key: 'platform_integrations_ops', file: 'platform-integrations-ops.yaml' },
    { key: 'revenue_insurance', file: 'revenue-insurance.yaml' },
    { key: 'scheduling_clinical', file: 'scheduling-clinical.yaml' },
    { key: 'tenancy_clinics', file: 'tenancy-clinics.yaml' },
];

const standardErrorSchema = {
    type: 'object',
    required: ['code', 'message', 'details', 'trace_id', 'correlation_id'],
    properties: {
        code: { type: 'string' },
        message: { type: 'string' },
        details: { type: 'object', additionalProperties: true },
        trace_id: { type: 'string', format: 'uuid' },
        correlation_id: { type: 'string', format: 'uuid' },
    },
};

const standardIdempotencyHeader = {
    name: 'Idempotency-Key',
    in: 'header',
    required: true,
    description: 'Required for protected mutation routes that opt into the shared idempotency contract.',
    schema: {
        type: 'string',
        minLength: 8,
        maxLength: 255,
    },
};

const responseHeaders = {
    RequestIdHeader: {
        description: 'Stable request identifier generated or accepted for this HTTP request.',
        schema: { type: 'string', format: 'uuid' },
    },
    CorrelationIdHeader: {
        description: 'Correlation identifier propagated across related requests, jobs, and events.',
        schema: { type: 'string', format: 'uuid' },
    },
    CausationIdHeader: {
        description: 'Causation identifier for the immediate parent request or event when available.',
        schema: { type: 'string', format: 'uuid' },
    },
};

const tagDescriptions = {
    Audit: 'Audit and compliance workflows.',
    Billing: 'Billing, invoicing, and payments.',
    Compliance: 'Consent and data-governance operations.',
    IdentityAccess: 'Authentication, IAM, and RBAC.',
    Integrations: 'External provider and integration-management endpoints.',
    Notifications: 'Notification templates, dispatch, and channel settings.',
    Ops: 'Operational status, metrics, and administrative controls.',
    Patient: 'Patient-owned records and read models.',
    Provider: 'Provider directories and scheduling ownership.',
    Reporting: 'Generated reports and artifact lifecycle.',
    Scheduling: 'Appointments, calendars, and availability.',
    Shared: 'Reference data and shared search endpoints.',
    TenantManagement: 'Tenants, clinics, departments, and rooms.',
    Treatment: 'Treatment plans, encounters, and clinical workflows.',
};

function main() {
    const bundle = createBundle();
    mkdirSync(openApiDir, { recursive: true });
    writeFileSync(path.join(openApiDir, 'openapi.json'), `${JSON.stringify(sortDeep(bundle), null, 2)}\n`);
    writeFileSync(path.join(openApiDir, 'openapi.yaml'), YAML.stringify(sortDeep(bundle), { lineWidth: 0 }));
}

function createBundle() {
    const bundle = {
        openapi: '3.1.1',
        jsonSchemaDialect: 'https://json-schema.org/draft/2020-12/schema',
        info: {
            title: 'MedFlow API',
            version: '0.1.0',
            description: 'Generated production OpenAPI bundle assembled from the repository fragment sources.',
        },
        servers: [{ url: '/' }],
        tags: [],
        paths: {},
        components: { headers: { ...responseHeaders } },
        security: [],
        'x-generated-from': fragments.map((fragment) => `docs/api/openapi/${fragment.file}`),
    };
    const tagNames = new Set();

    for (const fragment of fragments) {
        const document = parseFragment(fragment.file);
        normalizeFragment(document);

        const securitySchemeNames = mergeSecuritySchemes(bundle, fragment, document);
        const componentNames = prefixComponents(bundle, fragment, document);

        rewriteDocument(document.paths ?? {}, componentNames, securitySchemeNames);
        rewriteDocument(document.components ?? {}, componentNames, securitySchemeNames);

        mergePaths(bundle, document.paths ?? {});
        mergeComponents(bundle, document.components ?? {});

        collectTags(document.paths ?? {}, tagNames);
    }

    injectResponseHeaders(bundle.paths);
    bundle.tags = Array.from(tagNames)
        .sort((left, right) => left.localeCompare(right))
        .map((name) => tagDescriptions[name] ? { name, description: tagDescriptions[name] } : { name });

    return bundle;
}

function parseFragment(file) {
    const source = readFileSync(path.join(openApiDir, file), 'utf8');
    return YAML.parse(source);
}

function normalizeFragment(document) {
    if (document?.components?.schemas?.ApiErrorResponse) {
        document.components.schemas.ApiErrorResponse = structuredClone(standardErrorSchema);
    }
    if (document?.components?.parameters?.IdempotencyKeyHeader) {
        document.components.parameters.IdempotencyKeyHeader = structuredClone(standardIdempotencyHeader);
    }
}

function mergeSecuritySchemes(bundle, fragment, document) {
    const names = new Map();
    const schemes = document?.components?.securitySchemes ?? {};
    bundle.components.securitySchemes ??= {};

    for (const [name, scheme] of Object.entries(schemes)) {
        if (!bundle.components.securitySchemes[name]) {
            bundle.components.securitySchemes[name] = scheme;
            names.set(name, name);
            continue;
        }

        if (stableStringify(bundle.components.securitySchemes[name]) === stableStringify(scheme)) {
            names.set(name, name);
            continue;
        }

        const renamed = `${fragment.key}_${name}`;
        bundle.components.securitySchemes[renamed] = scheme;
        names.set(name, renamed);
    }

    return names;
}

function prefixComponents(bundle, fragment, document) {
    const names = new Map();

    for (const [section, values] of Object.entries(document?.components ?? {})) {
        if (section === 'securitySchemes' || typeof values !== 'object' || values === null) {
            continue;
        }

        document.components[section] = Object.fromEntries(
            Object.entries(values).map(([name, value]) => {
                const renamed = `${fragment.key}_${name}`;
                names.set(`${section}:${name}`, renamed);
                return [renamed, value];
            }),
        );
        bundle.components[section] ??= {};
    }

    return names;
}

function rewriteDocument(node, componentNames, securitySchemeNames) {
    if (Array.isArray(node)) {
        node.forEach((item) => rewriteDocument(item, componentNames, securitySchemeNames));
        return;
    }

    if (!node || typeof node !== 'object') {
        return;
    }

    if (typeof node.$ref === 'string' && node.$ref.startsWith('#/components/')) {
        const [, , section, name] = node.$ref.split('/');
        const renamed = componentNames.get(`${section}:${name}`);

        if (renamed) {
            node.$ref = `#/components/${section}/${renamed}`;
        }
    }

    if (Array.isArray(node.security)) {
        node.security = node.security.map((requirement) => Object.fromEntries(
            Object.entries(requirement).map(([name, scopes]) => [securitySchemeNames.get(name) ?? name, scopes]),
        ));
    }

    Object.values(node).forEach((value) => rewriteDocument(value, componentNames, securitySchemeNames));
}

function mergePaths(bundle, paths) {
    for (const [routePath, pathItem] of Object.entries(paths)) {
        bundle.paths[routePath] ??= {};

        for (const [method, operation] of Object.entries(pathItem)) {
            if (bundle.paths[routePath][method]) {
                throw new Error(`Duplicate documented operation: ${method.toUpperCase()} ${routePath}`);
            }

            bundle.paths[routePath][method] = operation;
        }
    }
}

function mergeComponents(bundle, components) {
    for (const [section, values] of Object.entries(components)) {
        if (typeof values !== 'object' || values === null) {
            continue;
        }

        bundle.components[section] ??= {};
        Object.assign(bundle.components[section], values);
    }
}

function collectTags(paths, tagNames) {
    for (const pathItem of Object.values(paths)) {
        for (const operation of Object.values(pathItem)) {
            for (const tag of operation.tags ?? []) {
                tagNames.add(tag);
            }
        }
    }
}

function injectResponseHeaders(paths) {
    for (const pathItem of Object.values(paths)) {
        for (const operation of Object.values(pathItem)) {
            operation.security ??= [];

            for (const [statusCode, response] of Object.entries(operation.responses ?? {})) {
                if (!/^[23]\d{2}$/.test(statusCode)) {
                    continue;
                }

                response.headers ??= {};
                response.headers['X-Request-Id'] ??= { $ref: '#/components/headers/RequestIdHeader' };
                response.headers['X-Correlation-Id'] ??= { $ref: '#/components/headers/CorrelationIdHeader' };
                response.headers['X-Causation-Id'] ??= { $ref: '#/components/headers/CausationIdHeader' };
            }
        }
    }
}

function stableStringify(value) {
    return JSON.stringify(sortDeep(value));
}

function sortDeep(value) {
    if (Array.isArray(value)) {
        return value.map(sortDeep);
    }

    if (!value || typeof value !== 'object') {
        return value;
    }

    return Object.fromEntries(
        Object.entries(value)
            .sort(([left], [right]) => left.localeCompare(right))
            .map(([key, child]) => [key, sortDeep(child)]),
    );
}

main();
