--
-- PostgreSQL database dump
--

\restrict WbfpfS3yR6G3eW0YwT9MCJZDVAnuaASfi1HK9nfhjTKEcJ9mPvcBuW155WwPQyK

-- Dumped from database version 17.6
-- Dumped by pg_dump version 18.2

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: auth; Type: SCHEMA; Schema: -; Owner: supabase_admin
--

CREATE SCHEMA auth;


ALTER SCHEMA auth OWNER TO supabase_admin;

--
-- Name: extensions; Type: SCHEMA; Schema: -; Owner: postgres
--

CREATE SCHEMA extensions;


ALTER SCHEMA extensions OWNER TO postgres;

--
-- Name: graphql; Type: SCHEMA; Schema: -; Owner: supabase_admin
--

CREATE SCHEMA graphql;


ALTER SCHEMA graphql OWNER TO supabase_admin;

--
-- Name: graphql_public; Type: SCHEMA; Schema: -; Owner: supabase_admin
--

CREATE SCHEMA graphql_public;


ALTER SCHEMA graphql_public OWNER TO supabase_admin;

--
-- Name: pgbouncer; Type: SCHEMA; Schema: -; Owner: pgbouncer
--

CREATE SCHEMA pgbouncer;


ALTER SCHEMA pgbouncer OWNER TO pgbouncer;

--
-- Name: public; Type: SCHEMA; Schema: -; Owner: postgres
--

-- *not* creating schema, since initdb creates it


ALTER SCHEMA public OWNER TO postgres;

--
-- Name: realtime; Type: SCHEMA; Schema: -; Owner: supabase_admin
--

CREATE SCHEMA realtime;


ALTER SCHEMA realtime OWNER TO supabase_admin;

--
-- Name: storage; Type: SCHEMA; Schema: -; Owner: supabase_admin
--

CREATE SCHEMA storage;


ALTER SCHEMA storage OWNER TO supabase_admin;

--
-- Name: vault; Type: SCHEMA; Schema: -; Owner: supabase_admin
--

CREATE SCHEMA vault;


ALTER SCHEMA vault OWNER TO supabase_admin;

--
-- Name: pg_stat_statements; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_stat_statements WITH SCHEMA extensions;


--
-- Name: EXTENSION pg_stat_statements; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION pg_stat_statements IS 'track planning and execution statistics of all SQL statements executed';


--
-- Name: pgcrypto; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA extensions;


--
-- Name: EXTENSION pgcrypto; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION pgcrypto IS 'cryptographic functions';


--
-- Name: supabase_vault; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS supabase_vault WITH SCHEMA vault;


--
-- Name: EXTENSION supabase_vault; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION supabase_vault IS 'Supabase Vault Extension';


--
-- Name: uuid-ossp; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS "uuid-ossp" WITH SCHEMA extensions;


--
-- Name: EXTENSION "uuid-ossp"; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION "uuid-ossp" IS 'generate universally unique identifiers (UUIDs)';


--
-- Name: aal_level; Type: TYPE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TYPE auth.aal_level AS ENUM (
    'aal1',
    'aal2',
    'aal3'
);


ALTER TYPE auth.aal_level OWNER TO supabase_auth_admin;

--
-- Name: code_challenge_method; Type: TYPE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TYPE auth.code_challenge_method AS ENUM (
    's256',
    'plain'
);


ALTER TYPE auth.code_challenge_method OWNER TO supabase_auth_admin;

--
-- Name: factor_status; Type: TYPE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TYPE auth.factor_status AS ENUM (
    'unverified',
    'verified'
);


ALTER TYPE auth.factor_status OWNER TO supabase_auth_admin;

--
-- Name: factor_type; Type: TYPE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TYPE auth.factor_type AS ENUM (
    'totp',
    'webauthn',
    'phone'
);


ALTER TYPE auth.factor_type OWNER TO supabase_auth_admin;

--
-- Name: oauth_authorization_status; Type: TYPE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TYPE auth.oauth_authorization_status AS ENUM (
    'pending',
    'approved',
    'denied',
    'expired'
);


ALTER TYPE auth.oauth_authorization_status OWNER TO supabase_auth_admin;

--
-- Name: oauth_client_type; Type: TYPE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TYPE auth.oauth_client_type AS ENUM (
    'public',
    'confidential'
);


ALTER TYPE auth.oauth_client_type OWNER TO supabase_auth_admin;

--
-- Name: oauth_registration_type; Type: TYPE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TYPE auth.oauth_registration_type AS ENUM (
    'dynamic',
    'manual'
);


ALTER TYPE auth.oauth_registration_type OWNER TO supabase_auth_admin;

--
-- Name: oauth_response_type; Type: TYPE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TYPE auth.oauth_response_type AS ENUM (
    'code'
);


ALTER TYPE auth.oauth_response_type OWNER TO supabase_auth_admin;

--
-- Name: one_time_token_type; Type: TYPE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TYPE auth.one_time_token_type AS ENUM (
    'confirmation_token',
    'reauthentication_token',
    'recovery_token',
    'email_change_token_new',
    'email_change_token_current',
    'phone_change_token'
);


ALTER TYPE auth.one_time_token_type OWNER TO supabase_auth_admin;

--
-- Name: action; Type: TYPE; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE TYPE realtime.action AS ENUM (
    'INSERT',
    'UPDATE',
    'DELETE',
    'TRUNCATE',
    'ERROR'
);


ALTER TYPE realtime.action OWNER TO supabase_realtime_admin;

--
-- Name: equality_op; Type: TYPE; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE TYPE realtime.equality_op AS ENUM (
    'eq',
    'neq',
    'lt',
    'lte',
    'gt',
    'gte',
    'in',
    'like',
    'ilike',
    'is',
    'match',
    'imatch',
    'isdistinct'
);


ALTER TYPE realtime.equality_op OWNER TO supabase_realtime_admin;

--
-- Name: user_defined_filter; Type: TYPE; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE TYPE realtime.user_defined_filter AS (
	column_name text,
	op realtime.equality_op,
	value text,
	negate boolean
);


ALTER TYPE realtime.user_defined_filter OWNER TO supabase_realtime_admin;

--
-- Name: wal_column; Type: TYPE; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE TYPE realtime.wal_column AS (
	name text,
	type_name text,
	type_oid oid,
	value jsonb,
	is_pkey boolean,
	is_selectable boolean
);


ALTER TYPE realtime.wal_column OWNER TO supabase_realtime_admin;

--
-- Name: wal_rls; Type: TYPE; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE TYPE realtime.wal_rls AS (
	wal jsonb,
	is_rls_enabled boolean,
	subscription_ids uuid[],
	errors text[]
);


ALTER TYPE realtime.wal_rls OWNER TO supabase_realtime_admin;

--
-- Name: buckettype; Type: TYPE; Schema: storage; Owner: supabase_storage_admin
--

CREATE TYPE storage.buckettype AS ENUM (
    'STANDARD',
    'ANALYTICS',
    'VECTOR'
);


ALTER TYPE storage.buckettype OWNER TO supabase_storage_admin;

--
-- Name: email(); Type: FUNCTION; Schema: auth; Owner: supabase_auth_admin
--

CREATE FUNCTION auth.email() RETURNS text
    LANGUAGE sql STABLE
    AS $$
  select 
  coalesce(
    nullif(current_setting('request.jwt.claim.email', true), ''),
    (nullif(current_setting('request.jwt.claims', true), '')::jsonb ->> 'email')
  )::text
$$;


ALTER FUNCTION auth.email() OWNER TO supabase_auth_admin;

--
-- Name: FUNCTION email(); Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON FUNCTION auth.email() IS 'Deprecated. Use auth.jwt() -> ''email'' instead.';


--
-- Name: jwt(); Type: FUNCTION; Schema: auth; Owner: supabase_auth_admin
--

CREATE FUNCTION auth.jwt() RETURNS jsonb
    LANGUAGE sql STABLE
    AS $$
  select 
    coalesce(
        nullif(current_setting('request.jwt.claim', true), ''),
        nullif(current_setting('request.jwt.claims', true), '')
    )::jsonb
$$;


ALTER FUNCTION auth.jwt() OWNER TO supabase_auth_admin;

--
-- Name: role(); Type: FUNCTION; Schema: auth; Owner: supabase_auth_admin
--

CREATE FUNCTION auth.role() RETURNS text
    LANGUAGE sql STABLE
    AS $$
  select 
  coalesce(
    nullif(current_setting('request.jwt.claim.role', true), ''),
    (nullif(current_setting('request.jwt.claims', true), '')::jsonb ->> 'role')
  )::text
$$;


ALTER FUNCTION auth.role() OWNER TO supabase_auth_admin;

--
-- Name: FUNCTION role(); Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON FUNCTION auth.role() IS 'Deprecated. Use auth.jwt() -> ''role'' instead.';


--
-- Name: uid(); Type: FUNCTION; Schema: auth; Owner: supabase_auth_admin
--

CREATE FUNCTION auth.uid() RETURNS uuid
    LANGUAGE sql STABLE
    AS $$
  select 
  coalesce(
    nullif(current_setting('request.jwt.claim.sub', true), ''),
    (nullif(current_setting('request.jwt.claims', true), '')::jsonb ->> 'sub')
  )::uuid
$$;


ALTER FUNCTION auth.uid() OWNER TO supabase_auth_admin;

--
-- Name: FUNCTION uid(); Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON FUNCTION auth.uid() IS 'Deprecated. Use auth.jwt() -> ''sub'' instead.';


--
-- Name: grant_pg_cron_access(); Type: FUNCTION; Schema: extensions; Owner: supabase_admin
--

CREATE FUNCTION extensions.grant_pg_cron_access() RETURNS event_trigger
    LANGUAGE plpgsql
    SET search_path TO ''
    AS $$
BEGIN
  IF EXISTS (
    SELECT
    FROM pg_event_trigger_ddl_commands() AS ev
    JOIN pg_extension AS ext
    ON ev.objid = ext.oid
    WHERE ext.extname = 'pg_cron'
  )
  THEN
    grant usage on schema cron to postgres with grant option;

    alter default privileges in schema cron grant all on tables to postgres with grant option;
    alter default privileges in schema cron grant all on functions to postgres with grant option;
    alter default privileges in schema cron grant all on sequences to postgres with grant option;

    alter default privileges for user supabase_admin in schema cron grant all
        on sequences to postgres with grant option;
    alter default privileges for user supabase_admin in schema cron grant all
        on tables to postgres with grant option;
    alter default privileges for user supabase_admin in schema cron grant all
        on functions to postgres with grant option;

    grant all privileges on all tables in schema cron to postgres with grant option;
    revoke all on table cron.job from postgres;
    grant select on table cron.job to postgres with grant option;
    revoke trigger on cron.job_run_details from postgres;
  END IF;
END;
$$;


ALTER FUNCTION extensions.grant_pg_cron_access() OWNER TO supabase_admin;

--
-- Name: FUNCTION grant_pg_cron_access(); Type: COMMENT; Schema: extensions; Owner: supabase_admin
--

COMMENT ON FUNCTION extensions.grant_pg_cron_access() IS 'Grants access to pg_cron';


--
-- Name: grant_pg_graphql_access(); Type: FUNCTION; Schema: extensions; Owner: supabase_admin
--

CREATE FUNCTION extensions.grant_pg_graphql_access() RETURNS event_trigger
    LANGUAGE plpgsql
    SET search_path TO ''
    AS $_$
begin
    if not exists (
        select 1
        from pg_catalog.pg_event_trigger_ddl_commands() ev
        join pg_catalog.pg_extension e on ev.objid = e.oid
        where e.extname = 'pg_graphql'
    ) then
        return;
    end if;

    drop function if exists graphql_public.graphql;
    create or replace function graphql_public.graphql(
        "operationName" text default null,
        query text default null,
        variables jsonb default null,
        extensions jsonb default null
    )
        returns jsonb
        language sql
    as $$
        select graphql.resolve(
            query := query,
            variables := coalesce(variables, '{}'),
            "operationName" := "operationName",
            extensions := extensions
        );
    $$;

    -- Attach the wrapper to the extension so DROP EXTENSION cascades to it,
    -- which in turn triggers set_graphql_placeholder to reinstall the "not enabled" stub.
    alter extension pg_graphql add function graphql_public.graphql(text, text, jsonb, jsonb);

    grant usage on schema graphql to postgres, anon, authenticated, service_role;
    grant execute on function graphql.resolve to postgres, anon, authenticated, service_role;
    grant usage on schema graphql to postgres with grant option;
    grant usage on schema graphql_public to postgres with grant option;
end;
$_$;


ALTER FUNCTION extensions.grant_pg_graphql_access() OWNER TO supabase_admin;

--
-- Name: FUNCTION grant_pg_graphql_access(); Type: COMMENT; Schema: extensions; Owner: supabase_admin
--

COMMENT ON FUNCTION extensions.grant_pg_graphql_access() IS 'Grants access to pg_graphql';


--
-- Name: grant_pg_net_access(); Type: FUNCTION; Schema: extensions; Owner: supabase_admin
--

CREATE FUNCTION extensions.grant_pg_net_access() RETURNS event_trigger
    LANGUAGE plpgsql
    SET search_path TO ''
    AS $$
BEGIN
  IF EXISTS (
    SELECT 1
    FROM pg_event_trigger_ddl_commands() AS ev
    JOIN pg_extension AS ext
    ON ev.objid = ext.oid
    WHERE ext.extname = 'pg_net'
  )
  THEN
    IF NOT EXISTS (
      SELECT 1
      FROM pg_roles
      WHERE rolname = 'supabase_functions_admin'
    )
    THEN
      CREATE USER supabase_functions_admin NOINHERIT CREATEROLE LOGIN NOREPLICATION;
    END IF;

    GRANT USAGE ON SCHEMA net TO supabase_functions_admin, postgres, anon, authenticated, service_role;

    IF EXISTS (
      SELECT FROM pg_extension
      WHERE extname = 'pg_net'
      -- all versions in use on existing projects as of 2025-02-20
      -- version 0.12.0 onwards don't need these applied
      AND extversion IN ('0.2', '0.6', '0.7', '0.7.1', '0.8.0', '0.10.0', '0.11.0')
    ) THEN
      ALTER function net.http_get(url text, params jsonb, headers jsonb, timeout_milliseconds integer) SECURITY DEFINER;
      ALTER function net.http_post(url text, body jsonb, params jsonb, headers jsonb, timeout_milliseconds integer) SECURITY DEFINER;

      ALTER function net.http_get(url text, params jsonb, headers jsonb, timeout_milliseconds integer) SET search_path = net;
      ALTER function net.http_post(url text, body jsonb, params jsonb, headers jsonb, timeout_milliseconds integer) SET search_path = net;

      REVOKE ALL ON FUNCTION net.http_get(url text, params jsonb, headers jsonb, timeout_milliseconds integer) FROM PUBLIC;
      REVOKE ALL ON FUNCTION net.http_post(url text, body jsonb, params jsonb, headers jsonb, timeout_milliseconds integer) FROM PUBLIC;

      GRANT EXECUTE ON FUNCTION net.http_get(url text, params jsonb, headers jsonb, timeout_milliseconds integer) TO supabase_functions_admin, postgres, anon, authenticated, service_role;
      GRANT EXECUTE ON FUNCTION net.http_post(url text, body jsonb, params jsonb, headers jsonb, timeout_milliseconds integer) TO supabase_functions_admin, postgres, anon, authenticated, service_role;
    END IF;
  END IF;
END;
$$;


ALTER FUNCTION extensions.grant_pg_net_access() OWNER TO supabase_admin;

--
-- Name: FUNCTION grant_pg_net_access(); Type: COMMENT; Schema: extensions; Owner: supabase_admin
--

COMMENT ON FUNCTION extensions.grant_pg_net_access() IS 'Grants access to pg_net';


--
-- Name: pgrst_ddl_watch(); Type: FUNCTION; Schema: extensions; Owner: supabase_admin
--

CREATE FUNCTION extensions.pgrst_ddl_watch() RETURNS event_trigger
    LANGUAGE plpgsql
    SET search_path TO ''
    AS $$
DECLARE
  cmd record;
BEGIN
  FOR cmd IN SELECT * FROM pg_event_trigger_ddl_commands()
  LOOP
    IF cmd.command_tag IN (
      'CREATE SCHEMA', 'ALTER SCHEMA'
    , 'CREATE TABLE', 'CREATE TABLE AS', 'SELECT INTO', 'ALTER TABLE'
    , 'CREATE FOREIGN TABLE', 'ALTER FOREIGN TABLE'
    , 'CREATE VIEW', 'ALTER VIEW'
    , 'CREATE MATERIALIZED VIEW', 'ALTER MATERIALIZED VIEW'
    , 'CREATE FUNCTION', 'ALTER FUNCTION'
    , 'CREATE TRIGGER'
    , 'CREATE TYPE', 'ALTER TYPE'
    , 'CREATE RULE'
    , 'COMMENT'
    )
    -- don't notify in case of CREATE TEMP table or other objects created on pg_temp
    AND cmd.schema_name is distinct from 'pg_temp'
    THEN
      NOTIFY pgrst, 'reload schema';
    END IF;
  END LOOP;
END; $$;


ALTER FUNCTION extensions.pgrst_ddl_watch() OWNER TO supabase_admin;

--
-- Name: pgrst_drop_watch(); Type: FUNCTION; Schema: extensions; Owner: supabase_admin
--

CREATE FUNCTION extensions.pgrst_drop_watch() RETURNS event_trigger
    LANGUAGE plpgsql
    SET search_path TO ''
    AS $$
DECLARE
  obj record;
BEGIN
  FOR obj IN SELECT * FROM pg_event_trigger_dropped_objects()
  LOOP
    IF obj.object_type IN (
      'schema'
    , 'table'
    , 'foreign table'
    , 'view'
    , 'materialized view'
    , 'function'
    , 'trigger'
    , 'type'
    , 'rule'
    )
    AND obj.is_temporary IS false -- no pg_temp objects
    THEN
      NOTIFY pgrst, 'reload schema';
    END IF;
  END LOOP;
END; $$;


ALTER FUNCTION extensions.pgrst_drop_watch() OWNER TO supabase_admin;

--
-- Name: set_graphql_placeholder(); Type: FUNCTION; Schema: extensions; Owner: supabase_admin
--

CREATE FUNCTION extensions.set_graphql_placeholder() RETURNS event_trigger
    LANGUAGE plpgsql
    SET search_path TO ''
    AS $_$
    DECLARE
    graphql_is_dropped bool;
    BEGIN
    graphql_is_dropped = (
        SELECT ev.schema_name = 'graphql_public'
        FROM pg_event_trigger_dropped_objects() AS ev
        WHERE ev.schema_name = 'graphql_public'
    );

    IF graphql_is_dropped
    THEN
        create or replace function graphql_public.graphql(
            "operationName" text default null,
            query text default null,
            variables jsonb default null,
            extensions jsonb default null
        )
            returns jsonb
            language plpgsql
            set search_path to ''
        as $$
            DECLARE
                server_version float;
            BEGIN
                server_version = (SELECT (SPLIT_PART((select version()), ' ', 2))::float);

                IF server_version >= 14 THEN
                    RETURN jsonb_build_object(
                        'errors', jsonb_build_array(
                            jsonb_build_object(
                                'message', 'pg_graphql extension is not enabled.'
                            )
                        )
                    );
                ELSE
                    RETURN jsonb_build_object(
                        'errors', jsonb_build_array(
                            jsonb_build_object(
                                'message', 'pg_graphql is only available on projects running Postgres 14 onwards.'
                            )
                        )
                    );
                END IF;
            END;
        $$;
    END IF;

    END;
$_$;


ALTER FUNCTION extensions.set_graphql_placeholder() OWNER TO supabase_admin;

--
-- Name: FUNCTION set_graphql_placeholder(); Type: COMMENT; Schema: extensions; Owner: supabase_admin
--

COMMENT ON FUNCTION extensions.set_graphql_placeholder() IS 'Reintroduces placeholder function for graphql_public.graphql';


--
-- Name: graphql(text, text, jsonb, jsonb); Type: FUNCTION; Schema: graphql_public; Owner: supabase_admin
--

CREATE FUNCTION graphql_public.graphql("operationName" text DEFAULT NULL::text, query text DEFAULT NULL::text, variables jsonb DEFAULT NULL::jsonb, extensions jsonb DEFAULT NULL::jsonb) RETURNS jsonb
    LANGUAGE plpgsql
    AS $$
            DECLARE
                server_version float;
            BEGIN
                server_version = (SELECT (SPLIT_PART((select version()), ' ', 2))::float);

                IF server_version >= 14 THEN
                    RETURN jsonb_build_object(
                        'errors', jsonb_build_array(
                            jsonb_build_object(
                                'message', 'pg_graphql extension is not enabled.'
                            )
                        )
                    );
                ELSE
                    RETURN jsonb_build_object(
                        'errors', jsonb_build_array(
                            jsonb_build_object(
                                'message', 'pg_graphql is only available on projects running Postgres 14 onwards.'
                            )
                        )
                    );
                END IF;
            END;
        $$;


ALTER FUNCTION graphql_public.graphql("operationName" text, query text, variables jsonb, extensions jsonb) OWNER TO supabase_admin;

--
-- Name: get_auth(text); Type: FUNCTION; Schema: pgbouncer; Owner: supabase_admin
--

CREATE FUNCTION pgbouncer.get_auth(p_usename text) RETURNS TABLE(username text, password text)
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO ''
    AS $_$
  BEGIN
      RAISE DEBUG 'PgBouncer auth request: %', p_usename;

      RETURN QUERY
      SELECT
          rolname::text,
          CASE WHEN rolvaliduntil < now()
              THEN null
              ELSE rolpassword::text
          END
      FROM pg_authid
      WHERE rolname=$1 and rolcanlogin;
  END;
  $_$;


ALTER FUNCTION pgbouncer.get_auth(p_usename text) OWNER TO supabase_admin;

--
-- Name: apply_rls(jsonb, integer); Type: FUNCTION; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE FUNCTION realtime.apply_rls(wal jsonb, max_record_bytes integer DEFAULT (1024 * 1024)) RETURNS SETOF realtime.wal_rls
    LANGUAGE plpgsql
    AS $$
declare
    -- Regclass of the table e.g. public.notes
    entity_ regclass = (quote_ident(wal ->> 'schema') || '.' || quote_ident(wal ->> 'table'))::regclass;

    -- I, U, D, T: insert, update ...
    action realtime.action = (
        case wal ->> 'action'
            when 'I' then 'INSERT'
            when 'U' then 'UPDATE'
            when 'D' then 'DELETE'
            else 'ERROR'
        end
    );

    -- Is row level security enabled for the table
    is_rls_enabled bool = relrowsecurity from pg_class where oid = entity_;

    subscriptions realtime.subscription[] = array_agg(subs)
        from
            realtime.subscription subs
        where
            subs.entity = entity_
            -- Filter by action early - only get subscriptions interested in this action
            -- action_filter column can be: '*' (all), 'INSERT', 'UPDATE', or 'DELETE'
            and (subs.action_filter = '*' or subs.action_filter = action::text);

    -- Subscription vars
    working_role regrole;
    working_selected_columns text[];
    claimed_role regrole;
    claims jsonb;

    subscription_id uuid;
    subscription_has_access bool;
    visible_to_subscription_ids uuid[] = '{}';

    -- structured info for wal's columns
    columns realtime.wal_column[];
    -- previous identity values for update/delete
    old_columns realtime.wal_column[];

    error_record_exceeds_max_size boolean = octet_length(wal::text) > max_record_bytes;

    -- Primary jsonb output for record
    output jsonb;

    -- Loop record for iterating unique roles (outer loop)
    role_record record;
    -- Loop record for iterating unique selected_columns within a role (inner loop)
    cols_record record;
    -- Subscription ids visible at the role level (before fanning out by selected_columns)
    visible_role_sub_ids uuid[] = '{}';

begin
    perform set_config('role', null, true);

    columns =
        array_agg(
            (
                x->>'name',
                x->>'type',
                x->>'typeoid',
                realtime.cast(
                    (x->'value') #>> '{}',
                    coalesce(
                        (x->>'typeoid')::regtype, -- null when wal2json version <= 2.4
                        (x->>'type')::regtype
                    )
                ),
                (pks ->> 'name') is not null,
                true
            )::realtime.wal_column
        )
        from
            jsonb_array_elements(wal -> 'columns') x
            left join jsonb_array_elements(wal -> 'pk') pks
                on (x ->> 'name') = (pks ->> 'name');

    old_columns =
        array_agg(
            (
                x->>'name',
                x->>'type',
                x->>'typeoid',
                realtime.cast(
                    (x->'value') #>> '{}',
                    coalesce(
                        (x->>'typeoid')::regtype, -- null when wal2json version <= 2.4
                        (x->>'type')::regtype
                    )
                ),
                (pks ->> 'name') is not null,
                true
            )::realtime.wal_column
        )
        from
            jsonb_array_elements(wal -> 'identity') x
            left join jsonb_array_elements(wal -> 'pk') pks
                on (x ->> 'name') = (pks ->> 'name');

    for role_record in
        select claims_role
        from (select distinct claims_role from unnest(subscriptions)) t
        order by claims_role::text
    loop
        working_role := role_record.claims_role;

        -- Update `is_selectable` for columns and old_columns (once per role)
        columns =
            array_agg(
                (
                    c.name,
                    c.type_name,
                    c.type_oid,
                    c.value,
                    c.is_pkey,
                    pg_catalog.has_column_privilege(working_role, entity_, c.name, 'SELECT')
                )::realtime.wal_column
            )
            from
                unnest(columns) c;

        old_columns =
                array_agg(
                    (
                        c.name,
                        c.type_name,
                        c.type_oid,
                        c.value,
                        c.is_pkey,
                        pg_catalog.has_column_privilege(working_role, entity_, c.name, 'SELECT')
                    )::realtime.wal_column
                )
                from
                    unnest(old_columns) c;

        if action <> 'DELETE' and count(1) = 0 from unnest(columns) c where c.is_pkey then
            -- Fan out 400 error per distinct selected_columns for this role
            for cols_record in
                select selected_columns
                from (select distinct selected_columns from unnest(subscriptions) s where s.claims_role = working_role) t
                order by coalesce(array_to_string(selected_columns, ','), '')
            loop
                working_selected_columns := cols_record.selected_columns;
                return next (
                    jsonb_build_object(
                        'schema', wal ->> 'schema',
                        'table', wal ->> 'table',
                        'type', action
                    ),
                    is_rls_enabled,
                    (select array_agg(s.subscription_id) from unnest(subscriptions) as s where s.claims_role = working_role and (s.selected_columns is not distinct from working_selected_columns)),
                    array['Error 400: Bad Request, no primary key']
                )::realtime.wal_rls;
            end loop;

        -- The claims role does not have SELECT permission to the primary key of entity
        elsif action <> 'DELETE' and sum(c.is_selectable::int) <> count(1) from unnest(columns) c where c.is_pkey then
            -- Fan out 401 error per distinct selected_columns for this role
            for cols_record in
                select selected_columns
                from (select distinct selected_columns from unnest(subscriptions) s where s.claims_role = working_role) t
                order by coalesce(array_to_string(selected_columns, ','), '')
            loop
                working_selected_columns := cols_record.selected_columns;
                return next (
                    jsonb_build_object(
                        'schema', wal ->> 'schema',
                        'table', wal ->> 'table',
                        'type', action
                    ),
                    is_rls_enabled,
                    (select array_agg(s.subscription_id) from unnest(subscriptions) as s where s.claims_role = working_role and (s.selected_columns is not distinct from working_selected_columns)),
                    array['Error 401: Unauthorized']
                )::realtime.wal_rls;
            end loop;

        else
            -- Create the prepared statement (once per role)
            if is_rls_enabled and action <> 'DELETE' then
                if (select 1 from pg_prepared_statements where name = 'walrus_rls_stmt' limit 1) > 0 then
                    deallocate walrus_rls_stmt;
                end if;
                execute realtime.build_prepared_statement_sql('walrus_rls_stmt', entity_, columns);
            end if;

            -- Collect all visible subscription IDs for this role (filter check + RLS check)
            visible_role_sub_ids = '{}';

            for subscription_id, claims in (
                    select
                        subs.subscription_id,
                        subs.claims
                    from
                        unnest(subscriptions) subs
                    where
                        subs.entity = entity_
                        and subs.claims_role = working_role
                        and (
                            realtime.is_visible_through_filters(columns, subs.filters)
                            or (
                              action = 'DELETE'
                              and realtime.is_visible_through_filters(old_columns, subs.filters)
                            )
                        )
            ) loop

                if not is_rls_enabled or action = 'DELETE' then
                    visible_role_sub_ids = visible_role_sub_ids || subscription_id;
                else
                    -- Check if RLS allows the role to see the record
                    perform
                        -- Trim leading and trailing quotes from working_role because set_config
                        -- doesn't recognize the role as valid if they are included
                        set_config('role', trim(both '"' from working_role::text), true),
                        set_config('request.jwt.claims', claims::text, true);

                    execute 'execute walrus_rls_stmt' into subscription_has_access;

                    -- Reset the role on every FOR..LOOP batch execution.
                    -- The first batch of 10 rows is pre-fetched using the current connection role (PG internal behaviour)
                    -- then we have to reset it again otherwise it would use the role defined in the `set_config` above
                    -- to fetch the remaining rows when rows>10, which could be a user-defined role that lacks execution grants.
                    -- The flow is:
                    --   1. run batch with conn role
                    --   2. set_config working_role
                    --   3. execute walrus
                    --   4. reset role (revert)
                    --   5. repeat
                    perform set_config('role', null, true);

                    if subscription_has_access then
                        visible_role_sub_ids = visible_role_sub_ids || subscription_id;
                    end if;
                end if;
            end loop;

            perform set_config('role', null, true);

            -- Inner loop: per distinct selected_columns for this role
            for cols_record in
                select selected_columns
                from (select distinct selected_columns from unnest(subscriptions) s where s.claims_role = working_role) t
                order by coalesce(array_to_string(selected_columns, ','), '')
            loop
                working_selected_columns := cols_record.selected_columns;

                output = jsonb_build_object(
                    'schema', wal ->> 'schema',
                    'table', wal ->> 'table',
                    'type', action,
                    'commit_timestamp', to_char(
                        ((wal ->> 'timestamp')::timestamptz at time zone 'utc'),
                        'YYYY-MM-DD"T"HH24:MI:SS.MS"Z"'
                    ),
                    'columns', (
                        select
                            jsonb_agg(
                                jsonb_build_object(
                                    'name', pa.attname,
                                    'type', pt.typname
                                )
                                order by pa.attnum asc
                            )
                        from
                            pg_attribute pa
                            join pg_type pt
                                on pa.atttypid = pt.oid
                            left join (
                                select unnest(conkey) as pkey_attnum
                                from pg_constraint
                                where conrelid = entity_ and contype = 'p'
                            ) pk on pk.pkey_attnum = pa.attnum
                        where
                            attrelid = entity_
                            and attnum > 0
                            and pg_catalog.has_column_privilege(working_role, entity_, pa.attname, 'SELECT')
                            and (working_selected_columns is null or pa.attname = any(working_selected_columns) or pk.pkey_attnum is not null)
                    )
                )
                -- Add "record" key for insert and update
                || case
                    when action in ('INSERT', 'UPDATE') then
                        jsonb_build_object(
                            'record',
                            (
                                select
                                    jsonb_object_agg(
                                        -- if unchanged toast, get column name and value from old record
                                        coalesce((c).name, (oc).name),
                                        case
                                            when (c).name is null then (oc).value
                                            else (c).value
                                        end
                                    )
                                from
                                    unnest(columns) c
                                    full outer join unnest(old_columns) oc
                                        on (c).name = (oc).name
                                where
                                    coalesce((c).is_selectable, (oc).is_selectable)
                                    and (working_selected_columns is null or coalesce((c).name, (oc).name) = any(working_selected_columns) or coalesce((c).is_pkey, (oc).is_pkey))
                                    and ( not error_record_exceeds_max_size or (octet_length((c).value::text) <= 64))
                            )
                        )
                    else '{}'::jsonb
                end
                -- Add "old_record" key for update and delete
                || case
                    when action = 'UPDATE' then
                        jsonb_build_object(
                                'old_record',
                                (
                                    select jsonb_object_agg((c).name, (c).value)
                                    from unnest(old_columns) c
                                    where
                                        (c).is_selectable
                                        and (working_selected_columns is null or (c).name = any(working_selected_columns) or (c).is_pkey)
                                        and ( not error_record_exceeds_max_size or (octet_length((c).value::text) <= 64))
                                )
                            )
                    when action = 'DELETE' then
                        jsonb_build_object(
                            'old_record',
                            (
                                select jsonb_object_agg((c).name, (c).value)
                                from unnest(old_columns) c
                                where
                                    (c).is_selectable
                                    and (working_selected_columns is null or (c).name = any(working_selected_columns) or (c).is_pkey)
                                    and ( not error_record_exceeds_max_size or (octet_length((c).value::text) <= 64))
                                    and ( not is_rls_enabled or (c).is_pkey ) -- if RLS enabled, we can't secure deletes so filter to pkey
                            )
                        )
                    else '{}'::jsonb
                end;

                -- Filter visible_role_sub_ids to those matching the current selected_columns group
                visible_to_subscription_ids = coalesce(
                    (
                        select array_agg(s.subscription_id)
                        from unnest(subscriptions) s
                        where s.claims_role = working_role
                          and (s.selected_columns is not distinct from working_selected_columns)
                          and s.subscription_id = any(visible_role_sub_ids)
                    ),
                    '{}'::uuid[]
                );

                return next (
                    output,
                    is_rls_enabled,
                    visible_to_subscription_ids,
                    case
                        when error_record_exceeds_max_size then array['Error 413: Payload Too Large']
                        else '{}'
                    end
                )::realtime.wal_rls;
            end loop;

        end if;
    end loop;

    perform set_config('role', null, true);
end;
$$;


ALTER FUNCTION realtime.apply_rls(wal jsonb, max_record_bytes integer) OWNER TO supabase_realtime_admin;

--
-- Name: broadcast_changes(text, text, text, text, text, record, record, text); Type: FUNCTION; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE FUNCTION realtime.broadcast_changes(topic_name text, event_name text, operation text, table_name text, table_schema text, new record, old record, level text DEFAULT 'ROW'::text) RETURNS void
    LANGUAGE plpgsql
    AS $$
DECLARE
    -- Declare a variable to hold the JSONB representation of the row
    row_data jsonb := '{}'::jsonb;
BEGIN
    IF level = 'STATEMENT' THEN
        RAISE EXCEPTION 'function can only be triggered for each row, not for each statement';
    END IF;
    -- Check the operation type and handle accordingly
    IF operation = 'INSERT' OR operation = 'UPDATE' OR operation = 'DELETE' THEN
        row_data := jsonb_build_object('old_record', OLD, 'record', NEW, 'operation', operation, 'table', table_name, 'schema', table_schema);
        PERFORM realtime.send (row_data, event_name, topic_name);
    ELSE
        RAISE EXCEPTION 'Unexpected operation type: %', operation;
    END IF;
EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'Failed to process the row: %', SQLERRM;
END;

$$;


ALTER FUNCTION realtime.broadcast_changes(topic_name text, event_name text, operation text, table_name text, table_schema text, new record, old record, level text) OWNER TO supabase_realtime_admin;

--
-- Name: build_prepared_statement_sql(text, regclass, realtime.wal_column[]); Type: FUNCTION; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE FUNCTION realtime.build_prepared_statement_sql(prepared_statement_name text, entity regclass, columns realtime.wal_column[]) RETURNS text
    LANGUAGE sql
    AS $$
      /*
      Builds a sql string that, if executed, creates a prepared statement to
      tests retrive a row from *entity* by its primary key columns.
      Example
          select realtime.build_prepared_statement_sql('public.notes', '{"id"}'::text[], '{"bigint"}'::text[])
      */
          select
      'prepare ' || prepared_statement_name || ' as
          select
              exists(
                  select
                      1
                  from
                      ' || entity || '
                  where
                      ' || string_agg(quote_ident(pkc.name) || '=' || quote_nullable(pkc.value #>> '{}') , ' and ') || '
              )'
          from
              unnest(columns) pkc
          where
              pkc.is_pkey
          group by
              entity
      $$;


ALTER FUNCTION realtime.build_prepared_statement_sql(prepared_statement_name text, entity regclass, columns realtime.wal_column[]) OWNER TO supabase_realtime_admin;

--
-- Name: cast(text, regtype); Type: FUNCTION; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE FUNCTION realtime."cast"(val text, type_ regtype) RETURNS jsonb
    LANGUAGE plpgsql IMMUTABLE
    AS $$
declare
  res jsonb;
begin
  if type_::text = 'bytea' then
    return to_jsonb(val);
  end if;
  execute format('select to_jsonb(%L::'|| type_::text || ')', val) into res;
  return res;
end
$$;


ALTER FUNCTION realtime."cast"(val text, type_ regtype) OWNER TO supabase_realtime_admin;

--
-- Name: check_equality_op(realtime.equality_op, regtype, text, text); Type: FUNCTION; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE FUNCTION realtime.check_equality_op(op realtime.equality_op, type_ regtype, val_1 text, val_2 text) RETURNS boolean
    LANGUAGE plpgsql IMMUTABLE
    AS $$
/*
Casts *val_1* and *val_2* as type *type_* and check the *op* condition for truthiness
*/
declare
    op_symbol text = (
        case
            when op = 'eq' then '='
            when op = 'neq' then '!='
            when op = 'lt' then '<'
            when op = 'lte' then '<='
            when op = 'gt' then '>'
            when op = 'gte' then '>='
            when op = 'in' then '= any'
            else 'UNKNOWN OP'
        end
    );
    res boolean;
begin
    execute format(
        'select %L::'|| type_::text || ' ' || op_symbol
        || ' ( %L::'
        || (
            case
                when op = 'in' then type_::text || '[]'
                else type_::text end
        )
        || ')', val_1, val_2) into res;
    return res;
end;
$$;


ALTER FUNCTION realtime.check_equality_op(op realtime.equality_op, type_ regtype, val_1 text, val_2 text) OWNER TO supabase_realtime_admin;

--
-- Name: check_equality_op(realtime.equality_op, regtype, text, text, boolean); Type: FUNCTION; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE FUNCTION realtime.check_equality_op(op realtime.equality_op, type_ regtype, val_1 text, val_2 text, negate boolean) RETURNS boolean
    LANGUAGE plpgsql STABLE
    AS $$
declare
    op_symbol text;
    res boolean;
begin
    -- IS DISTINCT FROM / IS NOT DISTINCT FROM: infix, both sides typed literals
    if op = 'isdistinct' then
        execute format(
            'select %L::%s %s %L::%s',
            val_1,
            type_::text,
            case when negate then 'IS NOT DISTINCT FROM' else 'IS DISTINCT FROM' end,
            val_2,
            type_::text
        ) into res;
        return res;
    end if;

    -- IS requires a keyword RHS (NULL, TRUE, FALSE, UNKNOWN), not a typed literal
    if op = 'is' then
        if val_2 not in ('null', 'true', 'false', 'unknown') then
            raise exception 'invalid value for is filter: must be null, true, false, or unknown';
        end if;
        execute format(
            'select %L::%s %s %s',
            val_1,
            type_::text,
            case when negate then 'IS NOT' else 'IS' end,
            upper(val_2)
        ) into res;
        return res;
    end if;

    op_symbol = case
        when op = 'eq'    then '='
        when op = 'neq'   then '!='
        when op = 'lt'    then '<'
        when op = 'lte'   then '<='
        when op = 'gt'    then '>'
        when op = 'gte'   then '>='
        when op = 'in'    then '= any'
        when op = 'like'   then 'LIKE'
        when op = 'ilike'  then 'ILIKE'
        when op = 'match'  then '~'
        when op = 'imatch' then '~*'
        else null
    end;

    if op_symbol is null then
        raise exception 'unsupported equality operator: %', op::text;
    end if;

    execute format(
        'select %L::%s %s (%L::%s)',
        val_1,
        type_::text,
        op_symbol,
        val_2,
        case when op = 'in' then type_::text || '[]' else type_::text end
    ) into res;

    return case when negate then not res else res end;
end;
$$;


ALTER FUNCTION realtime.check_equality_op(op realtime.equality_op, type_ regtype, val_1 text, val_2 text, negate boolean) OWNER TO supabase_realtime_admin;

--
-- Name: is_visible_through_filters(realtime.wal_column[], realtime.user_defined_filter[]); Type: FUNCTION; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE FUNCTION realtime.is_visible_through_filters(columns realtime.wal_column[], filters realtime.user_defined_filter[]) RETURNS boolean
    LANGUAGE sql STABLE
    AS $$
    select
        filters is null
        or array_length(filters, 1) is null
        or coalesce(
            count(col.name) = count(1)
            and sum(
                realtime.check_equality_op(
                    op:=f.op,
                    type_:=coalesce(col.type_oid::regtype, col.type_name::regtype),
                    val_1:=col.value #>> '{}',
                    val_2:=f.value,
                    negate:=coalesce(f.negate, false)
                )::int
            ) filter (where col.name is not null) = count(col.name),
            false
        )
    from
        unnest(filters) f
        left join unnest(columns) col
            on f.column_name = col.name;
$$;


ALTER FUNCTION realtime.is_visible_through_filters(columns realtime.wal_column[], filters realtime.user_defined_filter[]) OWNER TO supabase_realtime_admin;

--
-- Name: list_changes(name, name, integer, integer); Type: FUNCTION; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE FUNCTION realtime.list_changes(publication name, slot_name name, max_changes integer, max_record_bytes integer) RETURNS TABLE(wal jsonb, is_rls_enabled boolean, subscription_ids uuid[], errors text[], slot_changes_count bigint)
    LANGUAGE sql
    SET log_min_messages TO 'fatal'
    AS $$
  WITH pub AS (
    SELECT
      concat_ws(
        ',',
        CASE WHEN bool_or(pubinsert) THEN 'insert' ELSE NULL END,
        CASE WHEN bool_or(pubupdate) THEN 'update' ELSE NULL END,
        CASE WHEN bool_or(pubdelete) THEN 'delete' ELSE NULL END
      ) AS w2j_actions,
      coalesce(
        string_agg(
          realtime.quote_wal2json(format('%I.%I', schemaname, tablename)::regclass),
          ','
        ) filter (WHERE ppt.tablename IS NOT NULL),
        ''
      ) AS w2j_add_tables
    FROM pg_publication pp
    LEFT JOIN pg_publication_tables ppt ON pp.pubname = ppt.pubname
    WHERE pp.pubname = publication
    GROUP BY pp.pubname
    LIMIT 1
  ),
  -- MATERIALIZED ensures pg_logical_slot_get_changes is called exactly once
  w2j AS MATERIALIZED (
    SELECT x.*, pub.w2j_add_tables
    FROM pub,
         pg_logical_slot_get_changes(
           slot_name, null, max_changes,
           'include-pk', 'true',
           'include-transaction', 'false',
           'include-timestamp', 'true',
           'include-type-oids', 'true',
           'format-version', '2',
           'actions', pub.w2j_actions,
           'add-tables', pub.w2j_add_tables
         ) x
  ),
  slot_count AS (
    SELECT count(*)::bigint AS cnt
    FROM w2j
    WHERE w2j.w2j_add_tables <> ''
  ),
  rls_filtered AS (
    SELECT xyz.wal, xyz.is_rls_enabled, xyz.subscription_ids, xyz.errors
    FROM w2j,
         realtime.apply_rls(
           wal := w2j.data::jsonb,
           max_record_bytes := max_record_bytes
         ) xyz(wal, is_rls_enabled, subscription_ids, errors)
    WHERE w2j.w2j_add_tables <> ''
      AND xyz.subscription_ids[1] IS NOT NULL
  )
  SELECT rf.wal, rf.is_rls_enabled, rf.subscription_ids, rf.errors, sc.cnt
  FROM rls_filtered rf, slot_count sc

  UNION ALL

  SELECT null, null, null, null, sc.cnt
  FROM slot_count sc
  WHERE NOT EXISTS (SELECT 1 FROM rls_filtered)
$$;


ALTER FUNCTION realtime.list_changes(publication name, slot_name name, max_changes integer, max_record_bytes integer) OWNER TO supabase_realtime_admin;

--
-- Name: quote_wal2json(regclass); Type: FUNCTION; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE FUNCTION realtime.quote_wal2json(entity regclass) RETURNS text
    LANGUAGE sql IMMUTABLE STRICT
    AS $$
  SELECT
    realtime.wal2json_escape_identifier(nsp.nspname::text)
    || '.'
    || realtime.wal2json_escape_identifier(pc.relname::text)
  FROM pg_class pc
  JOIN pg_namespace nsp ON pc.relnamespace = nsp.oid
  WHERE pc.oid = entity
$$;


ALTER FUNCTION realtime.quote_wal2json(entity regclass) OWNER TO supabase_realtime_admin;

--
-- Name: send(jsonb, text, text, boolean); Type: FUNCTION; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE FUNCTION realtime.send(payload jsonb, event text, topic text, private boolean DEFAULT true) RETURNS void
    LANGUAGE plpgsql
    AS $$
DECLARE
  generated_id uuid;
  final_payload jsonb;
BEGIN
  BEGIN
    generated_id := gen_random_uuid();

    -- Check if payload has an 'id' key, if not, add the generated UUID
    IF payload ? 'id' THEN
      final_payload := payload;
    ELSE
      final_payload := jsonb_set(payload, '{id}', to_jsonb(generated_id));
    END IF;

    -- Set the topic configuration
    EXECUTE format('SET LOCAL realtime.topic TO %L', topic);

    INSERT INTO realtime.messages (id, payload, event, topic, private, extension)
    VALUES (generated_id, final_payload, event, topic, private, 'broadcast');
  EXCEPTION
    WHEN OTHERS THEN
      RAISE WARNING 'WarnSendingBroadcastMessage: %', SQLERRM;
  END;
END;
$$;


ALTER FUNCTION realtime.send(payload jsonb, event text, topic text, private boolean) OWNER TO supabase_realtime_admin;

--
-- Name: send_binary(bytea, text, text, boolean); Type: FUNCTION; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE FUNCTION realtime.send_binary(payload bytea, event text, topic text, private boolean DEFAULT true) RETURNS void
    LANGUAGE plpgsql
    AS $$
DECLARE
  generated_id uuid;
BEGIN
  BEGIN
    generated_id := gen_random_uuid();

    EXECUTE format('SET LOCAL realtime.topic TO %L', topic);

    INSERT INTO realtime.messages (id, binary_payload, event, topic, private, extension)
    VALUES (generated_id, payload, event, topic, private, 'broadcast');
  EXCEPTION
    WHEN OTHERS THEN
      RAISE WARNING 'WarnSendingBroadcastMessage: %', SQLERRM;
  END;
END;
$$;


ALTER FUNCTION realtime.send_binary(payload bytea, event text, topic text, private boolean) OWNER TO supabase_realtime_admin;

--
-- Name: subscription_check_filters(); Type: FUNCTION; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE FUNCTION realtime.subscription_check_filters() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
declare
    col_names text[] = coalesce(
            array_agg(a.attname order by a.attnum),
            '{}'::text[]
        )
        from
            pg_catalog.pg_attribute a
        where
            a.attrelid = new.entity
            and a.attnum > 0
            and not a.attisdropped
            and pg_catalog.has_column_privilege(
                (new.claims ->> 'role'),
                a.attrelid,
                a.attnum,
                'SELECT'
            );
    filter realtime.user_defined_filter;
    col_type regtype;
    in_val jsonb;
    selected_col text;
begin
    for filter in select * from unnest(new.filters) loop
        if not filter.column_name = any(col_names) then
            raise exception 'invalid column for filter %', filter.column_name;
        end if;

        col_type = (
            select atttypid::regtype
            from pg_catalog.pg_attribute
            where attrelid = new.entity
                  and attname = filter.column_name
        );
        if col_type is null then
            raise exception 'failed to lookup type for column %', filter.column_name;
        end if;

        if filter.op = 'in'::realtime.equality_op then
            in_val = realtime.cast(filter.value, (col_type::text || '[]')::regtype);
            if coalesce(jsonb_array_length(in_val), 0) > 100 then
                raise exception 'too many values for `in` filter. Maximum 100';
            end if;
        elsif filter.op = 'is'::realtime.equality_op then
            -- `is` requires a keyword RHS rather than a typed literal
            if filter.value not in ('null', 'true', 'false', 'unknown') then
                raise exception 'invalid value for is filter: must be null, true, false, or unknown';
            end if;
            -- IS NULL works for any type, but IS TRUE/FALSE/UNKNOWN require a boolean
            -- operand. Reject the non-null keywords on non-boolean columns here so they
            -- don't abort apply_rls at WAL time.
            if filter.value <> 'null' and col_type <> 'boolean'::regtype then
                raise exception 'is % filter requires a boolean column, got %', filter.value, col_type::text;
            end if;
        elsif filter.op in ('like'::realtime.equality_op, 'ilike'::realtime.equality_op) then
            -- like/ilike apply the text pattern operator (~~); reject column types that
            -- have no such operator instead of failing at WAL time
            if not exists (
                select 1 from pg_catalog.pg_operator
                where oprname = '~~' and oprleft = col_type
            ) then
                raise exception 'operator % requires a text-compatible column type, got %', filter.op::text, col_type::text;
            end if;
        elsif filter.op in ('match'::realtime.equality_op, 'imatch'::realtime.equality_op) then
            -- match/imatch apply the regex operators ~ / ~*; reject column types that have
            -- no such operator (e.g. integer) instead of failing at WAL time, mirroring the
            -- like/ilike guard above.
            if not exists (
                select 1 from pg_catalog.pg_operator
                where oprname = case when filter.op = 'imatch'::realtime.equality_op then '~*' else '~' end
                  and oprleft = col_type
                  and oprright = col_type
                  and oprresult = 'boolean'::regtype
            ) then
                raise exception 'operator % requires a text-compatible column type, got %', filter.op::text, col_type::text;
            end if;
            -- validate the regex eagerly so a bad pattern is rejected here, not inside
            -- apply_rls where it would abort the WAL stream for the entity
            begin
                perform '' ~ filter.value;
            exception when others then
                raise exception 'invalid regular expression for % filter: %', filter.op::text, sqlerrm;
            end;
        else
            -- eq/neq/lt/lte/gt/gte: value must be coercable to the type
            perform realtime.cast(filter.value, col_type);
        end if;
    end loop;

    if new.selected_columns is not null then
        for selected_col in select * from unnest(new.selected_columns) loop
            if not selected_col = any(col_names) then
                raise exception 'invalid column for select %', selected_col;
            end if;
        end loop;
    end if;

    -- Apply consistent order to filters so the unique constraint can't be tricked by a
    -- different filter order. negate is part of the sort key.
    new.filters = coalesce(
        array_agg(f order by f.column_name, f.op, f.value, f.negate),
        '{}'
    ) from unnest(new.filters) f;

    new.selected_columns = (
        select array_agg(c order by c)
        from unnest(new.selected_columns) c
    );

    return new;
end;
$$;


ALTER FUNCTION realtime.subscription_check_filters() OWNER TO supabase_realtime_admin;

--
-- Name: to_regrole(text); Type: FUNCTION; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE FUNCTION realtime.to_regrole(role_name text) RETURNS regrole
    LANGUAGE sql IMMUTABLE
    AS $$ select role_name::regrole $$;


ALTER FUNCTION realtime.to_regrole(role_name text) OWNER TO supabase_realtime_admin;

--
-- Name: topic(); Type: FUNCTION; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE FUNCTION realtime.topic() RETURNS text
    LANGUAGE sql STABLE
    AS $$
select nullif(current_setting('realtime.topic', true), '')::text;
$$;


ALTER FUNCTION realtime.topic() OWNER TO supabase_realtime_admin;

--
-- Name: wal2json_escape_identifier(text); Type: FUNCTION; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE FUNCTION realtime.wal2json_escape_identifier(name text) RETURNS text
    LANGUAGE sql IMMUTABLE STRICT
    AS $$
  -- Prefix `\`, `,`, `.`, and any whitespace with `\`
  SELECT regexp_replace(name, '([\\,.[:space:]])', '\\\1', 'g')
$$;


ALTER FUNCTION realtime.wal2json_escape_identifier(name text) OWNER TO supabase_realtime_admin;

--
-- Name: allow_any_operation(text[]); Type: FUNCTION; Schema: storage; Owner: supabase_storage_admin
--

CREATE FUNCTION storage.allow_any_operation(expected_operations text[]) RETURNS boolean
    LANGUAGE sql STABLE
    AS $$
  WITH current_operation AS (
    SELECT storage.operation() AS raw_operation
  ),
  normalized AS (
    SELECT CASE
      WHEN raw_operation LIKE 'storage.%' THEN substr(raw_operation, 9)
      ELSE raw_operation
    END AS current_operation
    FROM current_operation
  )
  SELECT EXISTS (
    SELECT 1
    FROM normalized n
    CROSS JOIN LATERAL unnest(expected_operations) AS expected_operation
    WHERE expected_operation IS NOT NULL
      AND expected_operation <> ''
      AND n.current_operation = CASE
        WHEN expected_operation LIKE 'storage.%' THEN substr(expected_operation, 9)
        ELSE expected_operation
      END
  );
$$;


ALTER FUNCTION storage.allow_any_operation(expected_operations text[]) OWNER TO supabase_storage_admin;

--
-- Name: allow_only_operation(text); Type: FUNCTION; Schema: storage; Owner: supabase_storage_admin
--

CREATE FUNCTION storage.allow_only_operation(expected_operation text) RETURNS boolean
    LANGUAGE sql STABLE
    AS $$
  WITH current_operation AS (
    SELECT storage.operation() AS raw_operation
  ),
  normalized AS (
    SELECT
      CASE
        WHEN raw_operation LIKE 'storage.%' THEN substr(raw_operation, 9)
        ELSE raw_operation
      END AS current_operation,
      CASE
        WHEN expected_operation LIKE 'storage.%' THEN substr(expected_operation, 9)
        ELSE expected_operation
      END AS requested_operation
    FROM current_operation
  )
  SELECT CASE
    WHEN requested_operation IS NULL OR requested_operation = '' THEN FALSE
    ELSE COALESCE(current_operation = requested_operation, FALSE)
  END
  FROM normalized;
$$;


ALTER FUNCTION storage.allow_only_operation(expected_operation text) OWNER TO supabase_storage_admin;

--
-- Name: can_insert_object(text, text, uuid, jsonb); Type: FUNCTION; Schema: storage; Owner: supabase_storage_admin
--

CREATE FUNCTION storage.can_insert_object(bucketid text, name text, owner uuid, metadata jsonb) RETURNS void
    LANGUAGE plpgsql
    AS $$
BEGIN
  INSERT INTO "storage"."objects" ("bucket_id", "name", "owner", "metadata") VALUES (bucketid, name, owner, metadata);
  -- hack to rollback the successful insert
  RAISE sqlstate 'PT200' using
  message = 'ROLLBACK',
  detail = 'rollback successful insert';
END
$$;


ALTER FUNCTION storage.can_insert_object(bucketid text, name text, owner uuid, metadata jsonb) OWNER TO supabase_storage_admin;

--
-- Name: enforce_bucket_name_length(); Type: FUNCTION; Schema: storage; Owner: supabase_storage_admin
--

CREATE FUNCTION storage.enforce_bucket_name_length() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
begin
    if length(new.name) > 100 then
        raise exception 'bucket name "%" is too long (% characters). Max is 100.', new.name, length(new.name);
    end if;
    return new;
end;
$$;


ALTER FUNCTION storage.enforce_bucket_name_length() OWNER TO supabase_storage_admin;

--
-- Name: extension(text); Type: FUNCTION; Schema: storage; Owner: supabase_storage_admin
--

CREATE FUNCTION storage.extension(name text) RETURNS text
    LANGUAGE plpgsql IMMUTABLE
    AS $$
DECLARE
    _parts text[];
    _filename text;
BEGIN
    -- Split on "/" to get path segments
    SELECT string_to_array(name, '/') INTO _parts;
    -- Get the last path segment (the actual filename)
    SELECT _parts[array_length(_parts, 1)] INTO _filename;
    -- Extract extension: reverse, split on '.', then reverse again
    RETURN reverse(split_part(reverse(_filename), '.', 1));
END
$$;


ALTER FUNCTION storage.extension(name text) OWNER TO supabase_storage_admin;

--
-- Name: filename(text); Type: FUNCTION; Schema: storage; Owner: supabase_storage_admin
--

CREATE FUNCTION storage.filename(name text) RETURNS text
    LANGUAGE plpgsql IMMUTABLE
    AS $$
DECLARE
    _parts text[];
BEGIN
    SELECT string_to_array(name, '/') INTO _parts;
    RETURN _parts[array_length(_parts, 1)];
END
$$;


ALTER FUNCTION storage.filename(name text) OWNER TO supabase_storage_admin;

--
-- Name: foldername(text); Type: FUNCTION; Schema: storage; Owner: supabase_storage_admin
--

CREATE FUNCTION storage.foldername(name text) RETURNS text[]
    LANGUAGE plpgsql IMMUTABLE
    AS $$
DECLARE
    _parts text[];
BEGIN
    -- Split on "/" to get path segments
    SELECT string_to_array(name, '/') INTO _parts;
    -- Return everything except the last segment
    RETURN _parts[1 : array_length(_parts,1) - 1];
END
$$;


ALTER FUNCTION storage.foldername(name text) OWNER TO supabase_storage_admin;

--
-- Name: get_common_prefix(text, text, text); Type: FUNCTION; Schema: storage; Owner: supabase_storage_admin
--

CREATE FUNCTION storage.get_common_prefix(p_key text, p_prefix text, p_delimiter text) RETURNS text
    LANGUAGE sql IMMUTABLE
    AS $$
SELECT CASE
    WHEN position(p_delimiter IN substring(p_key FROM length(p_prefix) + 1)) > 0
    THEN left(p_key, length(p_prefix) + position(p_delimiter IN substring(p_key FROM length(p_prefix) + 1)))
    ELSE NULL
END;
$$;


ALTER FUNCTION storage.get_common_prefix(p_key text, p_prefix text, p_delimiter text) OWNER TO supabase_storage_admin;

--
-- Name: get_size_by_bucket(); Type: FUNCTION; Schema: storage; Owner: supabase_storage_admin
--

CREATE FUNCTION storage.get_size_by_bucket() RETURNS TABLE(size bigint, bucket_id text)
    LANGUAGE plpgsql STABLE
    AS $$
BEGIN
    return query
        select sum((metadata->>'size')::bigint)::bigint as size, obj.bucket_id
        from "storage".objects as obj
        group by obj.bucket_id;
END
$$;


ALTER FUNCTION storage.get_size_by_bucket() OWNER TO supabase_storage_admin;

--
-- Name: list_multipart_uploads_with_delimiter(text, text, text, integer, text, text); Type: FUNCTION; Schema: storage; Owner: supabase_storage_admin
--

CREATE FUNCTION storage.list_multipart_uploads_with_delimiter(bucket_id text, prefix_param text, delimiter_param text, max_keys integer DEFAULT 100, next_key_token text DEFAULT ''::text, next_upload_token text DEFAULT ''::text) RETURNS TABLE(key text, id text, created_at timestamp with time zone)
    LANGUAGE plpgsql
    AS $_$
BEGIN
    RETURN QUERY EXECUTE
        'SELECT DISTINCT ON(key COLLATE "C") * from (
            SELECT
                CASE
                    WHEN position($2 IN substring(key from length($1) + 1)) > 0 THEN
                        substring(key from 1 for length($1) + position($2 IN substring(key from length($1) + 1)))
                    ELSE
                        key
                END AS key, id, created_at
            FROM
                storage.s3_multipart_uploads
            WHERE
                bucket_id = $5 AND
                key ILIKE $1 || ''%'' AND
                CASE
                    WHEN $4 != '''' AND $6 = '''' THEN
                        CASE
                            WHEN position($2 IN substring(key from length($1) + 1)) > 0 THEN
                                substring(key from 1 for length($1) + position($2 IN substring(key from length($1) + 1))) COLLATE "C" > $4
                            ELSE
                                key COLLATE "C" > $4
                            END
                    ELSE
                        true
                END AND
                CASE
                    WHEN $6 != '''' THEN
                        id COLLATE "C" > $6
                    ELSE
                        true
                    END
            ORDER BY
                key COLLATE "C" ASC, created_at ASC) as e order by key COLLATE "C" LIMIT $3'
        USING prefix_param, delimiter_param, max_keys, next_key_token, bucket_id, next_upload_token;
END;
$_$;


ALTER FUNCTION storage.list_multipart_uploads_with_delimiter(bucket_id text, prefix_param text, delimiter_param text, max_keys integer, next_key_token text, next_upload_token text) OWNER TO supabase_storage_admin;

--
-- Name: list_objects_with_delimiter(text, text, text, integer, text, text, text); Type: FUNCTION; Schema: storage; Owner: supabase_storage_admin
--

CREATE FUNCTION storage.list_objects_with_delimiter(_bucket_id text, prefix_param text, delimiter_param text, max_keys integer DEFAULT 100, start_after text DEFAULT ''::text, next_token text DEFAULT ''::text, sort_order text DEFAULT 'asc'::text) RETURNS TABLE(name text, id uuid, metadata jsonb, updated_at timestamp with time zone, created_at timestamp with time zone, last_accessed_at timestamp with time zone)
    LANGUAGE plpgsql STABLE
    AS $_$
DECLARE
    v_peek_name TEXT;
    v_current RECORD;
    v_common_prefix TEXT;

    -- Configuration
    v_is_asc BOOLEAN;
    v_prefix TEXT;
    v_start TEXT;
    v_upper_bound TEXT;
    v_file_batch_size INT;

    -- Seek state
    v_next_seek TEXT;
    v_count INT := 0;

    -- Dynamic SQL for batch query only
    v_batch_query TEXT;

BEGIN
    -- ========================================================================
    -- INITIALIZATION
    -- ========================================================================
    v_is_asc := lower(coalesce(sort_order, 'asc')) = 'asc';
    v_prefix := coalesce(prefix_param, '');
    v_start := CASE WHEN coalesce(next_token, '') <> '' THEN next_token ELSE coalesce(start_after, '') END;
    v_file_batch_size := LEAST(GREATEST(max_keys * 2, 100), 1000);

    -- Calculate upper bound for prefix filtering (bytewise, using COLLATE "C")
    IF v_prefix = '' THEN
        v_upper_bound := NULL;
    ELSIF right(v_prefix, 1) = delimiter_param THEN
        v_upper_bound := left(v_prefix, -1) || chr(ascii(delimiter_param) + 1);
    ELSE
        v_upper_bound := left(v_prefix, -1) || chr(ascii(right(v_prefix, 1)) + 1);
    END IF;

    -- Build batch query (dynamic SQL - called infrequently, amortized over many rows)
    IF v_is_asc THEN
        IF v_upper_bound IS NOT NULL THEN
            v_batch_query := 'SELECT o.name, o.id, o.updated_at, o.created_at, o.last_accessed_at, o.metadata ' ||
                'FROM storage.objects o WHERE o.bucket_id = $1 AND o.name COLLATE "C" >= $2 ' ||
                'AND o.name COLLATE "C" < $3 ORDER BY o.name COLLATE "C" ASC LIMIT $4';
        ELSE
            v_batch_query := 'SELECT o.name, o.id, o.updated_at, o.created_at, o.last_accessed_at, o.metadata ' ||
                'FROM storage.objects o WHERE o.bucket_id = $1 AND o.name COLLATE "C" >= $2 ' ||
                'ORDER BY o.name COLLATE "C" ASC LIMIT $4';
        END IF;
    ELSE
        IF v_upper_bound IS NOT NULL THEN
            v_batch_query := 'SELECT o.name, o.id, o.updated_at, o.created_at, o.last_accessed_at, o.metadata ' ||
                'FROM storage.objects o WHERE o.bucket_id = $1 AND o.name COLLATE "C" < $2 ' ||
                'AND o.name COLLATE "C" >= $3 ORDER BY o.name COLLATE "C" DESC LIMIT $4';
        ELSE
            v_batch_query := 'SELECT o.name, o.id, o.updated_at, o.created_at, o.last_accessed_at, o.metadata ' ||
                'FROM storage.objects o WHERE o.bucket_id = $1 AND o.name COLLATE "C" < $2 ' ||
                'ORDER BY o.name COLLATE "C" DESC LIMIT $4';
        END IF;
    END IF;

    -- ========================================================================
    -- SEEK INITIALIZATION: Determine starting position
    -- ========================================================================
    IF v_start = '' THEN
        IF v_is_asc THEN
            v_next_seek := v_prefix;
        ELSE
            -- DESC without cursor: find the last item in range
            IF v_upper_bound IS NOT NULL THEN
                SELECT o.name INTO v_next_seek FROM storage.objects o
                WHERE o.bucket_id = _bucket_id AND o.name COLLATE "C" >= v_prefix AND o.name COLLATE "C" < v_upper_bound
                ORDER BY o.name COLLATE "C" DESC LIMIT 1;
            ELSIF v_prefix <> '' THEN
                SELECT o.name INTO v_next_seek FROM storage.objects o
                WHERE o.bucket_id = _bucket_id AND o.name COLLATE "C" >= v_prefix
                ORDER BY o.name COLLATE "C" DESC LIMIT 1;
            ELSE
                SELECT o.name INTO v_next_seek FROM storage.objects o
                WHERE o.bucket_id = _bucket_id
                ORDER BY o.name COLLATE "C" DESC LIMIT 1;
            END IF;

            IF v_next_seek IS NOT NULL THEN
                v_next_seek := v_next_seek || delimiter_param;
            ELSE
                RETURN;
            END IF;
        END IF;
    ELSE
        -- Cursor provided: determine if it refers to a folder or leaf
        IF EXISTS (
            SELECT 1 FROM storage.objects o
            WHERE o.bucket_id = _bucket_id
              AND o.name COLLATE "C" LIKE v_start || delimiter_param || '%'
            LIMIT 1
        ) THEN
            -- Cursor refers to a folder
            IF v_is_asc THEN
                v_next_seek := v_start || chr(ascii(delimiter_param) + 1);
            ELSE
                v_next_seek := v_start || delimiter_param;
            END IF;
        ELSE
            -- Cursor refers to a leaf object
            IF v_is_asc THEN
                v_next_seek := v_start || delimiter_param;
            ELSE
                v_next_seek := v_start;
            END IF;
        END IF;
    END IF;

    -- ========================================================================
    -- MAIN LOOP: Hybrid peek-then-batch algorithm
    -- Uses STATIC SQL for peek (hot path) and DYNAMIC SQL for batch
    -- ========================================================================
    LOOP
        EXIT WHEN v_count >= max_keys;

        -- STEP 1: PEEK using STATIC SQL (plan cached, very fast)
        IF v_is_asc THEN
            IF v_upper_bound IS NOT NULL THEN
                SELECT o.name INTO v_peek_name FROM storage.objects o
                WHERE o.bucket_id = _bucket_id AND o.name COLLATE "C" >= v_next_seek AND o.name COLLATE "C" < v_upper_bound
                ORDER BY o.name COLLATE "C" ASC LIMIT 1;
            ELSE
                SELECT o.name INTO v_peek_name FROM storage.objects o
                WHERE o.bucket_id = _bucket_id AND o.name COLLATE "C" >= v_next_seek
                ORDER BY o.name COLLATE "C" ASC LIMIT 1;
            END IF;
        ELSE
            IF v_upper_bound IS NOT NULL THEN
                SELECT o.name INTO v_peek_name FROM storage.objects o
                WHERE o.bucket_id = _bucket_id AND o.name COLLATE "C" < v_next_seek AND o.name COLLATE "C" >= v_prefix
                ORDER BY o.name COLLATE "C" DESC LIMIT 1;
            ELSIF v_prefix <> '' THEN
                SELECT o.name INTO v_peek_name FROM storage.objects o
                WHERE o.bucket_id = _bucket_id AND o.name COLLATE "C" < v_next_seek AND o.name COLLATE "C" >= v_prefix
                ORDER BY o.name COLLATE "C" DESC LIMIT 1;
            ELSE
                SELECT o.name INTO v_peek_name FROM storage.objects o
                WHERE o.bucket_id = _bucket_id AND o.name COLLATE "C" < v_next_seek
                ORDER BY o.name COLLATE "C" DESC LIMIT 1;
            END IF;
        END IF;

        EXIT WHEN v_peek_name IS NULL;

        -- STEP 2: Check if this is a FOLDER or FILE
        v_common_prefix := storage.get_common_prefix(v_peek_name, v_prefix, delimiter_param);

        IF v_common_prefix IS NOT NULL THEN
            -- FOLDER: Emit and skip to next folder (no heap access needed)
            name := rtrim(v_common_prefix, delimiter_param);
            id := NULL;
            updated_at := NULL;
            created_at := NULL;
            last_accessed_at := NULL;
            metadata := NULL;
            RETURN NEXT;
            v_count := v_count + 1;

            -- Advance seek past the folder range
            IF v_is_asc THEN
                v_next_seek := left(v_common_prefix, -1) || chr(ascii(delimiter_param) + 1);
            ELSE
                v_next_seek := v_common_prefix;
            END IF;
        ELSE
            -- FILE: Batch fetch using DYNAMIC SQL (overhead amortized over many rows)
            -- For ASC: upper_bound is the exclusive upper limit (< condition)
            -- For DESC: prefix is the inclusive lower limit (>= condition)
            FOR v_current IN EXECUTE v_batch_query USING _bucket_id, v_next_seek,
                CASE WHEN v_is_asc THEN COALESCE(v_upper_bound, v_prefix) ELSE v_prefix END, v_file_batch_size
            LOOP
                v_common_prefix := storage.get_common_prefix(v_current.name, v_prefix, delimiter_param);

                IF v_common_prefix IS NOT NULL THEN
                    -- Hit a folder: exit batch, let peek handle it
                    v_next_seek := v_current.name;
                    EXIT;
                END IF;

                -- Emit file
                name := v_current.name;
                id := v_current.id;
                updated_at := v_current.updated_at;
                created_at := v_current.created_at;
                last_accessed_at := v_current.last_accessed_at;
                metadata := v_current.metadata;
                RETURN NEXT;
                v_count := v_count + 1;

                -- Advance seek past this file
                IF v_is_asc THEN
                    v_next_seek := v_current.name || delimiter_param;
                ELSE
                    v_next_seek := v_current.name;
                END IF;

                EXIT WHEN v_count >= max_keys;
            END LOOP;
        END IF;
    END LOOP;
END;
$_$;


ALTER FUNCTION storage.list_objects_with_delimiter(_bucket_id text, prefix_param text, delimiter_param text, max_keys integer, start_after text, next_token text, sort_order text) OWNER TO supabase_storage_admin;

--
-- Name: operation(); Type: FUNCTION; Schema: storage; Owner: supabase_storage_admin
--

CREATE FUNCTION storage.operation() RETURNS text
    LANGUAGE plpgsql STABLE
    AS $$
BEGIN
    RETURN current_setting('storage.operation', true);
END;
$$;


ALTER FUNCTION storage.operation() OWNER TO supabase_storage_admin;

--
-- Name: protect_delete(); Type: FUNCTION; Schema: storage; Owner: supabase_storage_admin
--

CREATE FUNCTION storage.protect_delete() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    -- Check if storage.allow_delete_query is set to 'true'
    IF COALESCE(current_setting('storage.allow_delete_query', true), 'false') != 'true' THEN
        RAISE EXCEPTION 'Direct deletion from storage tables is not allowed. Use the Storage API instead.'
            USING HINT = 'This prevents accidental data loss from orphaned objects.',
                  ERRCODE = '42501';
    END IF;
    RETURN NULL;
END;
$$;


ALTER FUNCTION storage.protect_delete() OWNER TO supabase_storage_admin;

--
-- Name: search(text, text, integer, integer, integer, text, text, text); Type: FUNCTION; Schema: storage; Owner: supabase_storage_admin
--

CREATE FUNCTION storage.search(prefix text, bucketname text, limits integer DEFAULT 100, levels integer DEFAULT 1, offsets integer DEFAULT 0, search text DEFAULT ''::text, sortcolumn text DEFAULT 'name'::text, sortorder text DEFAULT 'asc'::text) RETURNS TABLE(name text, id uuid, updated_at timestamp with time zone, created_at timestamp with time zone, last_accessed_at timestamp with time zone, metadata jsonb)
    LANGUAGE plpgsql STABLE
    AS $_$
DECLARE
    v_peek_name TEXT;
    v_current RECORD;
    v_common_prefix TEXT;
    v_delimiter CONSTANT TEXT := '/';

    -- Configuration
    v_limit INT;
    v_prefix TEXT;
    v_prefix_lower TEXT;
    v_prefix_len INT;
    v_prefix_start INT;
    v_combined_levels INT;
    v_is_asc BOOLEAN;
    v_order_by TEXT;
    v_sort_order TEXT;
    v_upper_bound TEXT;
    v_file_batch_size INT;

    -- Dynamic SQL for batch query only
    v_batch_query TEXT;

    -- Seek state
    v_next_seek TEXT;
    v_count INT := 0;
    v_skipped INT := 0;
BEGIN
    -- ========================================================================
    -- INITIALIZATION
    -- ========================================================================
    v_limit := LEAST(coalesce(limits, 100), 1500);
    v_prefix := coalesce(prefix, '') || coalesce(search, '');
    v_prefix_lower := lower(v_prefix);
    v_prefix_len := length(coalesce(prefix, ''));
    v_prefix_start := coalesce(array_length(string_to_array(coalesce(prefix, ''), v_delimiter), 1), 1);
    v_combined_levels := coalesce(array_length(string_to_array(v_prefix, v_delimiter), 1), 1);
    v_is_asc := lower(coalesce(sortorder, 'asc')) = 'asc';
    v_file_batch_size := LEAST(GREATEST(v_limit * 2, 100), 1000);

    -- Validate sort column
    CASE lower(coalesce(sortcolumn, 'name'))
        WHEN 'name' THEN v_order_by := 'name';
        WHEN 'updated_at' THEN v_order_by := 'updated_at';
        WHEN 'created_at' THEN v_order_by := 'created_at';
        WHEN 'last_accessed_at' THEN v_order_by := 'last_accessed_at';
        ELSE v_order_by := 'name';
    END CASE;

    v_sort_order := CASE WHEN v_is_asc THEN 'asc' ELSE 'desc' END;

    -- ========================================================================
    -- NON-NAME SORTING: Use path_tokens approach
    -- ========================================================================
    IF v_order_by != 'name' THEN
        RETURN QUERY EXECUTE format(
            $sql$
            WITH folders AS (
                SELECT array_to_string(path_tokens[$1:$2], '/') AS folder
                FROM storage.objects
                WHERE objects.name ILIKE $3 || '%%'
                  AND bucket_id = $4
                  AND array_length(objects.path_tokens, 1) <> $2
                GROUP BY folder
                ORDER BY folder %s
            )
            (SELECT folder AS "name",
                   NULL::uuid AS id,
                   NULL::timestamptz AS updated_at,
                   NULL::timestamptz AS created_at,
                   NULL::timestamptz AS last_accessed_at,
                   NULL::jsonb AS metadata FROM folders)
            UNION ALL
            (SELECT array_to_string(path_tokens[$1:$2], '/') AS "name",
                   id, updated_at, created_at, last_accessed_at, metadata
             FROM storage.objects
             WHERE objects.name ILIKE $3 || '%%'
               AND bucket_id = $4
               AND array_length(objects.path_tokens, 1) = $2
             ORDER BY %I %s)
            LIMIT $5 OFFSET $6
            $sql$, v_sort_order, v_order_by, v_sort_order
        ) USING v_prefix_start, v_combined_levels, v_prefix, bucketname, v_limit, offsets;
        RETURN;
    END IF;

    -- ========================================================================
    -- NAME SORTING: Hybrid skip-scan with batch optimization
    -- ========================================================================

    -- Calculate upper bound for prefix filtering
    IF v_prefix_lower = '' THEN
        v_upper_bound := NULL;
    ELSIF right(v_prefix_lower, 1) = v_delimiter THEN
        v_upper_bound := left(v_prefix_lower, -1) || chr(ascii(v_delimiter) + 1);
    ELSE
        v_upper_bound := left(v_prefix_lower, -1) || chr(ascii(right(v_prefix_lower, 1)) + 1);
    END IF;

    -- Build batch query (dynamic SQL - called infrequently, amortized over many rows)
    IF v_is_asc THEN
        IF v_upper_bound IS NOT NULL THEN
            v_batch_query := 'SELECT o.name, o.id, o.updated_at, o.created_at, o.last_accessed_at, o.metadata ' ||
                'FROM storage.objects o WHERE o.bucket_id = $1 AND lower(o.name) COLLATE "C" >= $2 ' ||
                'AND lower(o.name) COLLATE "C" < $3 ORDER BY lower(o.name) COLLATE "C" ASC LIMIT $4';
        ELSE
            v_batch_query := 'SELECT o.name, o.id, o.updated_at, o.created_at, o.last_accessed_at, o.metadata ' ||
                'FROM storage.objects o WHERE o.bucket_id = $1 AND lower(o.name) COLLATE "C" >= $2 ' ||
                'ORDER BY lower(o.name) COLLATE "C" ASC LIMIT $4';
        END IF;
    ELSE
        IF v_upper_bound IS NOT NULL THEN
            v_batch_query := 'SELECT o.name, o.id, o.updated_at, o.created_at, o.last_accessed_at, o.metadata ' ||
                'FROM storage.objects o WHERE o.bucket_id = $1 AND lower(o.name) COLLATE "C" < $2 ' ||
                'AND lower(o.name) COLLATE "C" >= $3 ORDER BY lower(o.name) COLLATE "C" DESC LIMIT $4';
        ELSE
            v_batch_query := 'SELECT o.name, o.id, o.updated_at, o.created_at, o.last_accessed_at, o.metadata ' ||
                'FROM storage.objects o WHERE o.bucket_id = $1 AND lower(o.name) COLLATE "C" < $2 ' ||
                'ORDER BY lower(o.name) COLLATE "C" DESC LIMIT $4';
        END IF;
    END IF;

    -- Initialize seek position
    IF v_is_asc THEN
        v_next_seek := v_prefix_lower;
    ELSE
        -- DESC: find the last item in range first (static SQL)
        IF v_upper_bound IS NOT NULL THEN
            SELECT o.name INTO v_peek_name FROM storage.objects o
            WHERE o.bucket_id = bucketname AND lower(o.name) COLLATE "C" >= v_prefix_lower AND lower(o.name) COLLATE "C" < v_upper_bound
            ORDER BY lower(o.name) COLLATE "C" DESC LIMIT 1;
        ELSIF v_prefix_lower <> '' THEN
            SELECT o.name INTO v_peek_name FROM storage.objects o
            WHERE o.bucket_id = bucketname AND lower(o.name) COLLATE "C" >= v_prefix_lower
            ORDER BY lower(o.name) COLLATE "C" DESC LIMIT 1;
        ELSE
            SELECT o.name INTO v_peek_name FROM storage.objects o
            WHERE o.bucket_id = bucketname
            ORDER BY lower(o.name) COLLATE "C" DESC LIMIT 1;
        END IF;

        IF v_peek_name IS NOT NULL THEN
            v_next_seek := lower(v_peek_name) || v_delimiter;
        ELSE
            RETURN;
        END IF;
    END IF;

    -- ========================================================================
    -- MAIN LOOP: Hybrid peek-then-batch algorithm
    -- Uses STATIC SQL for peek (hot path) and DYNAMIC SQL for batch
    -- ========================================================================
    LOOP
        EXIT WHEN v_count >= v_limit;

        -- STEP 1: PEEK using STATIC SQL (plan cached, very fast)
        IF v_is_asc THEN
            IF v_upper_bound IS NOT NULL THEN
                SELECT o.name INTO v_peek_name FROM storage.objects o
                WHERE o.bucket_id = bucketname AND lower(o.name) COLLATE "C" >= v_next_seek AND lower(o.name) COLLATE "C" < v_upper_bound
                ORDER BY lower(o.name) COLLATE "C" ASC LIMIT 1;
            ELSE
                SELECT o.name INTO v_peek_name FROM storage.objects o
                WHERE o.bucket_id = bucketname AND lower(o.name) COLLATE "C" >= v_next_seek
                ORDER BY lower(o.name) COLLATE "C" ASC LIMIT 1;
            END IF;
        ELSE
            IF v_upper_bound IS NOT NULL THEN
                SELECT o.name INTO v_peek_name FROM storage.objects o
                WHERE o.bucket_id = bucketname AND lower(o.name) COLLATE "C" < v_next_seek AND lower(o.name) COLLATE "C" >= v_prefix_lower
                ORDER BY lower(o.name) COLLATE "C" DESC LIMIT 1;
            ELSIF v_prefix_lower <> '' THEN
                SELECT o.name INTO v_peek_name FROM storage.objects o
                WHERE o.bucket_id = bucketname AND lower(o.name) COLLATE "C" < v_next_seek AND lower(o.name) COLLATE "C" >= v_prefix_lower
                ORDER BY lower(o.name) COLLATE "C" DESC LIMIT 1;
            ELSE
                SELECT o.name INTO v_peek_name FROM storage.objects o
                WHERE o.bucket_id = bucketname AND lower(o.name) COLLATE "C" < v_next_seek
                ORDER BY lower(o.name) COLLATE "C" DESC LIMIT 1;
            END IF;
        END IF;

        EXIT WHEN v_peek_name IS NULL;

        -- STEP 2: Check if this is a FOLDER or FILE
        v_common_prefix := storage.get_common_prefix(lower(v_peek_name), v_prefix_lower, v_delimiter);

        IF v_common_prefix IS NOT NULL THEN
            -- FOLDER: Handle offset, emit if needed, skip to next folder
            IF v_skipped < offsets THEN
                v_skipped := v_skipped + 1;
            ELSE
                name := substring(rtrim(storage.get_common_prefix(v_peek_name, v_prefix, v_delimiter), v_delimiter) from v_prefix_len + 1);
                id := NULL;
                updated_at := NULL;
                created_at := NULL;
                last_accessed_at := NULL;
                metadata := NULL;
                RETURN NEXT;
                v_count := v_count + 1;
            END IF;

            -- Advance seek past the folder range
            IF v_is_asc THEN
                v_next_seek := lower(left(v_common_prefix, -1)) || chr(ascii(v_delimiter) + 1);
            ELSE
                v_next_seek := lower(v_common_prefix);
            END IF;
        ELSE
            -- FILE: Batch fetch using DYNAMIC SQL (overhead amortized over many rows)
            -- For ASC: upper_bound is the exclusive upper limit (< condition)
            -- For DESC: prefix_lower is the inclusive lower limit (>= condition)
            FOR v_current IN EXECUTE v_batch_query
                USING bucketname, v_next_seek,
                    CASE WHEN v_is_asc THEN COALESCE(v_upper_bound, v_prefix_lower) ELSE v_prefix_lower END, v_file_batch_size
            LOOP
                v_common_prefix := storage.get_common_prefix(lower(v_current.name), v_prefix_lower, v_delimiter);

                IF v_common_prefix IS NOT NULL THEN
                    -- Hit a folder: exit batch, let peek handle it
                    v_next_seek := lower(v_current.name);
                    EXIT;
                END IF;

                -- Handle offset skipping
                IF v_skipped < offsets THEN
                    v_skipped := v_skipped + 1;
                ELSE
                    -- Emit file
                    name := substring(v_current.name from v_prefix_len + 1);
                    id := v_current.id;
                    updated_at := v_current.updated_at;
                    created_at := v_current.created_at;
                    last_accessed_at := v_current.last_accessed_at;
                    metadata := v_current.metadata;
                    RETURN NEXT;
                    v_count := v_count + 1;
                END IF;

                -- Advance seek past this file
                IF v_is_asc THEN
                    v_next_seek := lower(v_current.name) || v_delimiter;
                ELSE
                    v_next_seek := lower(v_current.name);
                END IF;

                EXIT WHEN v_count >= v_limit;
            END LOOP;
        END IF;
    END LOOP;
END;
$_$;


ALTER FUNCTION storage.search(prefix text, bucketname text, limits integer, levels integer, offsets integer, search text, sortcolumn text, sortorder text) OWNER TO supabase_storage_admin;

--
-- Name: search_by_timestamp(text, text, integer, integer, text, text, text, text); Type: FUNCTION; Schema: storage; Owner: supabase_storage_admin
--

CREATE FUNCTION storage.search_by_timestamp(p_prefix text, p_bucket_id text, p_limit integer, p_level integer, p_start_after text, p_sort_order text, p_sort_column text, p_sort_column_after text) RETURNS TABLE(key text, name text, id uuid, updated_at timestamp with time zone, created_at timestamp with time zone, last_accessed_at timestamp with time zone, metadata jsonb)
    LANGUAGE plpgsql STABLE
    AS $_$
DECLARE
    v_cursor_op text;
    v_query text;
    v_prefix text;
    v_sort_order text;
    v_sort_column text;
BEGIN
    v_prefix := coalesce(p_prefix, '');

    -- Defense-in-depth: this function is independently reachable and must
    -- not trust p_sort_order/p_sort_column to already be validated by a
    -- caller. Normalize to the same strict allow-list storage.search_v2
    -- uses before interpolating anything into dynamic SQL below.
    v_sort_order := lower(coalesce(p_sort_order, 'asc'));
    IF v_sort_order NOT IN ('asc', 'desc') THEN
        v_sort_order := 'asc';
    END IF;

    v_sort_column := lower(coalesce(p_sort_column, 'updated_at'));
    IF v_sort_column NOT IN ('updated_at', 'created_at') THEN
        v_sort_column := 'updated_at';
    END IF;

    IF v_sort_order = 'asc' THEN
        v_cursor_op := '>';
    ELSE
        v_cursor_op := '<';
    END IF;

    v_query := format($sql$
        WITH raw_objects AS (
            SELECT
                o.name AS obj_name,
                o.id AS obj_id,
                o.updated_at AS obj_updated_at,
                o.created_at AS obj_created_at,
                o.last_accessed_at AS obj_last_accessed_at,
                o.metadata AS obj_metadata,
                storage.get_common_prefix(o.name, $1, '/') AS common_prefix
            FROM storage.objects o
            WHERE o.bucket_id = $2
              AND o.name COLLATE "C" LIKE $1 || '%%'
        ),
        -- Aggregate common prefixes (folders)
        -- Both created_at and updated_at use MIN(obj_created_at) to match the old prefixes table behavior
        aggregated_prefixes AS (
            SELECT
                rtrim(common_prefix, '/') AS name,
                NULL::uuid AS id,
                MIN(obj_created_at) AS updated_at,
                MIN(obj_created_at) AS created_at,
                NULL::timestamptz AS last_accessed_at,
                NULL::jsonb AS metadata,
                TRUE AS is_prefix
            FROM raw_objects
            WHERE common_prefix IS NOT NULL
            GROUP BY common_prefix
        ),
        leaf_objects AS (
            SELECT
                obj_name AS name,
                obj_id AS id,
                obj_updated_at AS updated_at,
                obj_created_at AS created_at,
                obj_last_accessed_at AS last_accessed_at,
                obj_metadata AS metadata,
                FALSE AS is_prefix
            FROM raw_objects
            WHERE common_prefix IS NULL
        ),
        combined AS (
            SELECT * FROM aggregated_prefixes
            UNION ALL
            SELECT * FROM leaf_objects
        ),
        filtered AS (
            SELECT *
            FROM combined
            WHERE (
                $5 = ''
                OR ROW(
                    date_trunc('milliseconds', %I),
                    name COLLATE "C"
                ) %s ROW(
                    COALESCE(NULLIF($6, '')::timestamptz, 'epoch'::timestamptz),
                    $5
                )
            )
        )
        SELECT
            split_part(name, '/', $3) AS key,
            name,
            id,
            updated_at,
            created_at,
            last_accessed_at,
            metadata
        FROM filtered
        ORDER BY
            COALESCE(date_trunc('milliseconds', %I), 'epoch'::timestamptz) %s,
            name COLLATE "C" %s
        LIMIT $4
    $sql$,
        v_sort_column,
        v_cursor_op,
        v_sort_column,
        v_sort_order,
        v_sort_order
    );

    RETURN QUERY EXECUTE v_query
    USING v_prefix, p_bucket_id, p_level, p_limit, p_start_after, p_sort_column_after;
END;
$_$;


ALTER FUNCTION storage.search_by_timestamp(p_prefix text, p_bucket_id text, p_limit integer, p_level integer, p_start_after text, p_sort_order text, p_sort_column text, p_sort_column_after text) OWNER TO supabase_storage_admin;

--
-- Name: search_v2(text, text, integer, integer, text, text, text, text); Type: FUNCTION; Schema: storage; Owner: supabase_storage_admin
--

CREATE FUNCTION storage.search_v2(prefix text, bucket_name text, limits integer DEFAULT 100, levels integer DEFAULT 1, start_after text DEFAULT ''::text, sort_order text DEFAULT 'asc'::text, sort_column text DEFAULT 'name'::text, sort_column_after text DEFAULT ''::text) RETURNS TABLE(key text, name text, id uuid, updated_at timestamp with time zone, created_at timestamp with time zone, last_accessed_at timestamp with time zone, metadata jsonb)
    LANGUAGE plpgsql STABLE
    AS $$
DECLARE
    v_sort_col text;
    v_sort_ord text;
    v_limit int;
BEGIN
    -- Cap limit to maximum of 1500 records
    v_limit := LEAST(coalesce(limits, 100), 1500);

    -- Validate and normalize sort_order
    v_sort_ord := lower(coalesce(sort_order, 'asc'));
    IF v_sort_ord NOT IN ('asc', 'desc') THEN
        v_sort_ord := 'asc';
    END IF;

    -- Validate and normalize sort_column
    v_sort_col := lower(coalesce(sort_column, 'name'));
    IF v_sort_col NOT IN ('name', 'updated_at', 'created_at') THEN
        v_sort_col := 'name';
    END IF;

    -- Route to appropriate implementation
    IF v_sort_col = 'name' THEN
        -- Use list_objects_with_delimiter for name sorting (most efficient: O(k * log n))
        RETURN QUERY
        SELECT
            split_part(l.name, '/', levels) AS key,
            l.name AS name,
            l.id,
            l.updated_at,
            l.created_at,
            l.last_accessed_at,
            l.metadata
        FROM storage.list_objects_with_delimiter(
            bucket_name,
            coalesce(prefix, ''),
            '/',
            v_limit,
            start_after,
            '',
            v_sort_ord
        ) l;
    ELSE
        -- Use aggregation approach for timestamp sorting
        -- Not efficient for large datasets but supports correct pagination
        RETURN QUERY SELECT * FROM storage.search_by_timestamp(
            prefix, bucket_name, v_limit, levels, start_after,
            v_sort_ord, v_sort_col, sort_column_after
        );
    END IF;
END;
$$;


ALTER FUNCTION storage.search_v2(prefix text, bucket_name text, limits integer, levels integer, start_after text, sort_order text, sort_column text, sort_column_after text) OWNER TO supabase_storage_admin;

--
-- Name: update_updated_at_column(); Type: FUNCTION; Schema: storage; Owner: supabase_storage_admin
--

CREATE FUNCTION storage.update_updated_at_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW; 
END;
$$;


ALTER FUNCTION storage.update_updated_at_column() OWNER TO supabase_storage_admin;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: audit_log_entries; Type: TABLE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TABLE auth.audit_log_entries (
    instance_id uuid,
    id uuid NOT NULL,
    payload json,
    created_at timestamp with time zone,
    ip_address character varying(64) DEFAULT ''::character varying NOT NULL
);


ALTER TABLE auth.audit_log_entries OWNER TO supabase_auth_admin;

--
-- Name: TABLE audit_log_entries; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON TABLE auth.audit_log_entries IS 'Auth: Audit trail for user actions.';


--
-- Name: custom_oauth_providers; Type: TABLE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TABLE auth.custom_oauth_providers (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    provider_type text NOT NULL,
    identifier text NOT NULL,
    name text NOT NULL,
    client_id text NOT NULL,
    client_secret text NOT NULL,
    acceptable_client_ids text[] DEFAULT '{}'::text[] NOT NULL,
    scopes text[] DEFAULT '{}'::text[] NOT NULL,
    pkce_enabled boolean DEFAULT true NOT NULL,
    attribute_mapping jsonb DEFAULT '{}'::jsonb NOT NULL,
    authorization_params jsonb DEFAULT '{}'::jsonb NOT NULL,
    enabled boolean DEFAULT true NOT NULL,
    email_optional boolean DEFAULT false NOT NULL,
    issuer text,
    discovery_url text,
    skip_nonce_check boolean DEFAULT false NOT NULL,
    cached_discovery jsonb,
    discovery_cached_at timestamp with time zone,
    authorization_url text,
    token_url text,
    userinfo_url text,
    jwks_uri text,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    custom_claims_allowlist text[] DEFAULT '{}'::text[] NOT NULL,
    CONSTRAINT custom_oauth_providers_authorization_url_https CHECK (((authorization_url IS NULL) OR (authorization_url ~~ 'https://%'::text))),
    CONSTRAINT custom_oauth_providers_authorization_url_length CHECK (((authorization_url IS NULL) OR (char_length(authorization_url) <= 2048))),
    CONSTRAINT custom_oauth_providers_client_id_length CHECK (((char_length(client_id) >= 1) AND (char_length(client_id) <= 512))),
    CONSTRAINT custom_oauth_providers_discovery_url_length CHECK (((discovery_url IS NULL) OR (char_length(discovery_url) <= 2048))),
    CONSTRAINT custom_oauth_providers_identifier_format CHECK ((identifier ~ '^[a-z0-9][a-z0-9:-]{0,48}[a-z0-9]$'::text)),
    CONSTRAINT custom_oauth_providers_issuer_length CHECK (((issuer IS NULL) OR ((char_length(issuer) >= 1) AND (char_length(issuer) <= 2048)))),
    CONSTRAINT custom_oauth_providers_jwks_uri_https CHECK (((jwks_uri IS NULL) OR (jwks_uri ~~ 'https://%'::text))),
    CONSTRAINT custom_oauth_providers_jwks_uri_length CHECK (((jwks_uri IS NULL) OR (char_length(jwks_uri) <= 2048))),
    CONSTRAINT custom_oauth_providers_name_length CHECK (((char_length(name) >= 1) AND (char_length(name) <= 100))),
    CONSTRAINT custom_oauth_providers_oauth2_requires_endpoints CHECK (((provider_type <> 'oauth2'::text) OR ((authorization_url IS NOT NULL) AND (token_url IS NOT NULL) AND (userinfo_url IS NOT NULL)))),
    CONSTRAINT custom_oauth_providers_oidc_discovery_url_https CHECK (((provider_type <> 'oidc'::text) OR (discovery_url IS NULL) OR (discovery_url ~~ 'https://%'::text))),
    CONSTRAINT custom_oauth_providers_oidc_issuer_https CHECK (((provider_type <> 'oidc'::text) OR (issuer IS NULL) OR (issuer ~~ 'https://%'::text))),
    CONSTRAINT custom_oauth_providers_oidc_requires_issuer CHECK (((provider_type <> 'oidc'::text) OR (issuer IS NOT NULL))),
    CONSTRAINT custom_oauth_providers_provider_type_check CHECK ((provider_type = ANY (ARRAY['oauth2'::text, 'oidc'::text]))),
    CONSTRAINT custom_oauth_providers_token_url_https CHECK (((token_url IS NULL) OR (token_url ~~ 'https://%'::text))),
    CONSTRAINT custom_oauth_providers_token_url_length CHECK (((token_url IS NULL) OR (char_length(token_url) <= 2048))),
    CONSTRAINT custom_oauth_providers_userinfo_url_https CHECK (((userinfo_url IS NULL) OR (userinfo_url ~~ 'https://%'::text))),
    CONSTRAINT custom_oauth_providers_userinfo_url_length CHECK (((userinfo_url IS NULL) OR (char_length(userinfo_url) <= 2048)))
);


ALTER TABLE auth.custom_oauth_providers OWNER TO supabase_auth_admin;

--
-- Name: flow_state; Type: TABLE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TABLE auth.flow_state (
    id uuid NOT NULL,
    user_id uuid,
    auth_code text,
    code_challenge_method auth.code_challenge_method,
    code_challenge text,
    provider_type text NOT NULL,
    provider_access_token text,
    provider_refresh_token text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    authentication_method text NOT NULL,
    auth_code_issued_at timestamp with time zone,
    invite_token text,
    referrer text,
    oauth_client_state_id uuid,
    linking_target_id uuid,
    email_optional boolean DEFAULT false NOT NULL
);


ALTER TABLE auth.flow_state OWNER TO supabase_auth_admin;

--
-- Name: TABLE flow_state; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON TABLE auth.flow_state IS 'Stores metadata for all OAuth/SSO login flows';


--
-- Name: identities; Type: TABLE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TABLE auth.identities (
    provider_id text NOT NULL,
    user_id uuid NOT NULL,
    identity_data jsonb NOT NULL,
    provider text NOT NULL,
    last_sign_in_at timestamp with time zone,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    email text GENERATED ALWAYS AS (lower((identity_data ->> 'email'::text))) STORED,
    id uuid DEFAULT gen_random_uuid() NOT NULL
);


ALTER TABLE auth.identities OWNER TO supabase_auth_admin;

--
-- Name: TABLE identities; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON TABLE auth.identities IS 'Auth: Stores identities associated to a user.';


--
-- Name: COLUMN identities.email; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON COLUMN auth.identities.email IS 'Auth: Email is a generated column that references the optional email property in the identity_data';


--
-- Name: instances; Type: TABLE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TABLE auth.instances (
    id uuid NOT NULL,
    uuid uuid,
    raw_base_config text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


ALTER TABLE auth.instances OWNER TO supabase_auth_admin;

--
-- Name: TABLE instances; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON TABLE auth.instances IS 'Auth: Manages users across multiple sites.';


--
-- Name: mfa_amr_claims; Type: TABLE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TABLE auth.mfa_amr_claims (
    session_id uuid NOT NULL,
    created_at timestamp with time zone NOT NULL,
    updated_at timestamp with time zone NOT NULL,
    authentication_method text NOT NULL,
    id uuid NOT NULL
);


ALTER TABLE auth.mfa_amr_claims OWNER TO supabase_auth_admin;

--
-- Name: TABLE mfa_amr_claims; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON TABLE auth.mfa_amr_claims IS 'auth: stores authenticator method reference claims for multi factor authentication';


--
-- Name: mfa_challenges; Type: TABLE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TABLE auth.mfa_challenges (
    id uuid NOT NULL,
    factor_id uuid NOT NULL,
    created_at timestamp with time zone NOT NULL,
    verified_at timestamp with time zone,
    ip_address inet NOT NULL,
    otp_code text,
    web_authn_session_data jsonb
);


ALTER TABLE auth.mfa_challenges OWNER TO supabase_auth_admin;

--
-- Name: TABLE mfa_challenges; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON TABLE auth.mfa_challenges IS 'auth: stores metadata about challenge requests made';


--
-- Name: mfa_factors; Type: TABLE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TABLE auth.mfa_factors (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    friendly_name text,
    factor_type auth.factor_type NOT NULL,
    status auth.factor_status NOT NULL,
    created_at timestamp with time zone NOT NULL,
    updated_at timestamp with time zone NOT NULL,
    secret text,
    phone text,
    last_challenged_at timestamp with time zone,
    web_authn_credential jsonb,
    web_authn_aaguid uuid,
    last_webauthn_challenge_data jsonb
);


ALTER TABLE auth.mfa_factors OWNER TO supabase_auth_admin;

--
-- Name: TABLE mfa_factors; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON TABLE auth.mfa_factors IS 'auth: stores metadata about factors';


--
-- Name: COLUMN mfa_factors.last_webauthn_challenge_data; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON COLUMN auth.mfa_factors.last_webauthn_challenge_data IS 'Stores the latest WebAuthn challenge data including attestation/assertion for customer verification';


--
-- Name: oauth_authorizations; Type: TABLE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TABLE auth.oauth_authorizations (
    id uuid NOT NULL,
    authorization_id text NOT NULL,
    client_id uuid NOT NULL,
    user_id uuid,
    redirect_uri text NOT NULL,
    scope text NOT NULL,
    state text,
    resource text,
    code_challenge text,
    code_challenge_method auth.code_challenge_method,
    response_type auth.oauth_response_type DEFAULT 'code'::auth.oauth_response_type NOT NULL,
    status auth.oauth_authorization_status DEFAULT 'pending'::auth.oauth_authorization_status NOT NULL,
    authorization_code text,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    expires_at timestamp with time zone DEFAULT (now() + '00:03:00'::interval) NOT NULL,
    approved_at timestamp with time zone,
    nonce text,
    CONSTRAINT oauth_authorizations_authorization_code_length CHECK ((char_length(authorization_code) <= 255)),
    CONSTRAINT oauth_authorizations_code_challenge_length CHECK ((char_length(code_challenge) <= 128)),
    CONSTRAINT oauth_authorizations_expires_at_future CHECK ((expires_at > created_at)),
    CONSTRAINT oauth_authorizations_nonce_length CHECK ((char_length(nonce) <= 255)),
    CONSTRAINT oauth_authorizations_redirect_uri_length CHECK ((char_length(redirect_uri) <= 2048)),
    CONSTRAINT oauth_authorizations_resource_length CHECK ((char_length(resource) <= 2048)),
    CONSTRAINT oauth_authorizations_scope_length CHECK ((char_length(scope) <= 4096)),
    CONSTRAINT oauth_authorizations_state_length CHECK ((char_length(state) <= 4096))
);


ALTER TABLE auth.oauth_authorizations OWNER TO supabase_auth_admin;

--
-- Name: oauth_client_states; Type: TABLE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TABLE auth.oauth_client_states (
    id uuid NOT NULL,
    provider_type text NOT NULL,
    code_verifier text,
    created_at timestamp with time zone NOT NULL
);


ALTER TABLE auth.oauth_client_states OWNER TO supabase_auth_admin;

--
-- Name: TABLE oauth_client_states; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON TABLE auth.oauth_client_states IS 'Stores OAuth states for third-party provider authentication flows where Supabase acts as the OAuth client.';


--
-- Name: oauth_clients; Type: TABLE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TABLE auth.oauth_clients (
    id uuid NOT NULL,
    client_secret_hash text,
    registration_type auth.oauth_registration_type NOT NULL,
    redirect_uris text NOT NULL,
    grant_types text NOT NULL,
    client_name text,
    client_uri text,
    logo_uri text,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp with time zone,
    client_type auth.oauth_client_type DEFAULT 'confidential'::auth.oauth_client_type NOT NULL,
    token_endpoint_auth_method text NOT NULL,
    CONSTRAINT oauth_clients_client_name_length CHECK ((char_length(client_name) <= 1024)),
    CONSTRAINT oauth_clients_client_uri_length CHECK ((char_length(client_uri) <= 2048)),
    CONSTRAINT oauth_clients_logo_uri_length CHECK ((char_length(logo_uri) <= 2048)),
    CONSTRAINT oauth_clients_token_endpoint_auth_method_check CHECK ((token_endpoint_auth_method = ANY (ARRAY['client_secret_basic'::text, 'client_secret_post'::text, 'none'::text])))
);


ALTER TABLE auth.oauth_clients OWNER TO supabase_auth_admin;

--
-- Name: oauth_consents; Type: TABLE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TABLE auth.oauth_consents (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    client_id uuid NOT NULL,
    scopes text NOT NULL,
    granted_at timestamp with time zone DEFAULT now() NOT NULL,
    revoked_at timestamp with time zone,
    CONSTRAINT oauth_consents_revoked_after_granted CHECK (((revoked_at IS NULL) OR (revoked_at >= granted_at))),
    CONSTRAINT oauth_consents_scopes_length CHECK ((char_length(scopes) <= 2048)),
    CONSTRAINT oauth_consents_scopes_not_empty CHECK ((char_length(TRIM(BOTH FROM scopes)) > 0))
);


ALTER TABLE auth.oauth_consents OWNER TO supabase_auth_admin;

--
-- Name: one_time_tokens; Type: TABLE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TABLE auth.one_time_tokens (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    token_type auth.one_time_token_type NOT NULL,
    token_hash text NOT NULL,
    relates_to text NOT NULL,
    created_at timestamp without time zone DEFAULT now() NOT NULL,
    updated_at timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT one_time_tokens_token_hash_check CHECK ((char_length(token_hash) > 0))
);


ALTER TABLE auth.one_time_tokens OWNER TO supabase_auth_admin;

--
-- Name: refresh_tokens; Type: TABLE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TABLE auth.refresh_tokens (
    instance_id uuid,
    id bigint NOT NULL,
    token character varying(255),
    user_id character varying(255),
    revoked boolean,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    parent character varying(255),
    session_id uuid
);


ALTER TABLE auth.refresh_tokens OWNER TO supabase_auth_admin;

--
-- Name: TABLE refresh_tokens; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON TABLE auth.refresh_tokens IS 'Auth: Store of tokens used to refresh JWT tokens once they expire.';


--
-- Name: refresh_tokens_id_seq; Type: SEQUENCE; Schema: auth; Owner: supabase_auth_admin
--

CREATE SEQUENCE auth.refresh_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE auth.refresh_tokens_id_seq OWNER TO supabase_auth_admin;

--
-- Name: refresh_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: auth; Owner: supabase_auth_admin
--

ALTER SEQUENCE auth.refresh_tokens_id_seq OWNED BY auth.refresh_tokens.id;


--
-- Name: saml_providers; Type: TABLE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TABLE auth.saml_providers (
    id uuid NOT NULL,
    sso_provider_id uuid NOT NULL,
    entity_id text NOT NULL,
    metadata_xml text NOT NULL,
    metadata_url text,
    attribute_mapping jsonb,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    name_id_format text,
    CONSTRAINT "entity_id not empty" CHECK ((char_length(entity_id) > 0)),
    CONSTRAINT "metadata_url not empty" CHECK (((metadata_url = NULL::text) OR (char_length(metadata_url) > 0))),
    CONSTRAINT "metadata_xml not empty" CHECK ((char_length(metadata_xml) > 0))
);


ALTER TABLE auth.saml_providers OWNER TO supabase_auth_admin;

--
-- Name: TABLE saml_providers; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON TABLE auth.saml_providers IS 'Auth: Manages SAML Identity Provider connections.';


--
-- Name: saml_relay_states; Type: TABLE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TABLE auth.saml_relay_states (
    id uuid NOT NULL,
    sso_provider_id uuid NOT NULL,
    request_id text NOT NULL,
    for_email text,
    redirect_to text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    flow_state_id uuid,
    CONSTRAINT "request_id not empty" CHECK ((char_length(request_id) > 0))
);


ALTER TABLE auth.saml_relay_states OWNER TO supabase_auth_admin;

--
-- Name: TABLE saml_relay_states; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON TABLE auth.saml_relay_states IS 'Auth: Contains SAML Relay State information for each Service Provider initiated login.';


--
-- Name: schema_migrations; Type: TABLE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TABLE auth.schema_migrations (
    version character varying(255) NOT NULL
);


ALTER TABLE auth.schema_migrations OWNER TO supabase_auth_admin;

--
-- Name: TABLE schema_migrations; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON TABLE auth.schema_migrations IS 'Auth: Manages updates to the auth system.';


--
-- Name: sessions; Type: TABLE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TABLE auth.sessions (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    factor_id uuid,
    aal auth.aal_level,
    not_after timestamp with time zone,
    refreshed_at timestamp without time zone,
    user_agent text,
    ip inet,
    tag text,
    oauth_client_id uuid,
    refresh_token_hmac_key text,
    refresh_token_counter bigint,
    scopes text,
    CONSTRAINT sessions_scopes_length CHECK ((char_length(scopes) <= 4096))
);


ALTER TABLE auth.sessions OWNER TO supabase_auth_admin;

--
-- Name: TABLE sessions; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON TABLE auth.sessions IS 'Auth: Stores session data associated to a user.';


--
-- Name: COLUMN sessions.not_after; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON COLUMN auth.sessions.not_after IS 'Auth: Not after is a nullable column that contains a timestamp after which the session should be regarded as expired.';


--
-- Name: COLUMN sessions.refresh_token_hmac_key; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON COLUMN auth.sessions.refresh_token_hmac_key IS 'Holds a HMAC-SHA256 key used to sign refresh tokens for this session.';


--
-- Name: COLUMN sessions.refresh_token_counter; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON COLUMN auth.sessions.refresh_token_counter IS 'Holds the ID (counter) of the last issued refresh token.';


--
-- Name: sso_domains; Type: TABLE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TABLE auth.sso_domains (
    id uuid NOT NULL,
    sso_provider_id uuid NOT NULL,
    domain text NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT "domain not empty" CHECK ((char_length(domain) > 0))
);


ALTER TABLE auth.sso_domains OWNER TO supabase_auth_admin;

--
-- Name: TABLE sso_domains; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON TABLE auth.sso_domains IS 'Auth: Manages SSO email address domain mapping to an SSO Identity Provider.';


--
-- Name: sso_providers; Type: TABLE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TABLE auth.sso_providers (
    id uuid NOT NULL,
    resource_id text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    disabled boolean,
    CONSTRAINT "resource_id not empty" CHECK (((resource_id = NULL::text) OR (char_length(resource_id) > 0)))
);


ALTER TABLE auth.sso_providers OWNER TO supabase_auth_admin;

--
-- Name: TABLE sso_providers; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON TABLE auth.sso_providers IS 'Auth: Manages SSO identity provider information; see saml_providers for SAML.';


--
-- Name: COLUMN sso_providers.resource_id; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON COLUMN auth.sso_providers.resource_id IS 'Auth: Uniquely identifies a SSO provider according to a user-chosen resource ID (case insensitive), useful in infrastructure as code.';


--
-- Name: users; Type: TABLE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TABLE auth.users (
    instance_id uuid,
    id uuid NOT NULL,
    aud character varying(255),
    role character varying(255),
    email character varying(255),
    encrypted_password character varying(255),
    email_confirmed_at timestamp with time zone,
    invited_at timestamp with time zone,
    confirmation_token character varying(255),
    confirmation_sent_at timestamp with time zone,
    recovery_token character varying(255),
    recovery_sent_at timestamp with time zone,
    email_change_token_new character varying(255),
    email_change character varying(255),
    email_change_sent_at timestamp with time zone,
    last_sign_in_at timestamp with time zone,
    raw_app_meta_data jsonb,
    raw_user_meta_data jsonb,
    is_super_admin boolean,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    phone text DEFAULT NULL::character varying,
    phone_confirmed_at timestamp with time zone,
    phone_change text DEFAULT ''::character varying,
    phone_change_token character varying(255) DEFAULT ''::character varying,
    phone_change_sent_at timestamp with time zone,
    confirmed_at timestamp with time zone GENERATED ALWAYS AS (LEAST(email_confirmed_at, phone_confirmed_at)) STORED,
    email_change_token_current character varying(255) DEFAULT ''::character varying,
    email_change_confirm_status smallint DEFAULT 0,
    banned_until timestamp with time zone,
    reauthentication_token character varying(255) DEFAULT ''::character varying,
    reauthentication_sent_at timestamp with time zone,
    is_sso_user boolean DEFAULT false NOT NULL,
    deleted_at timestamp with time zone,
    is_anonymous boolean DEFAULT false NOT NULL,
    CONSTRAINT users_email_change_confirm_status_check CHECK (((email_change_confirm_status >= 0) AND (email_change_confirm_status <= 2)))
);


ALTER TABLE auth.users OWNER TO supabase_auth_admin;

--
-- Name: TABLE users; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON TABLE auth.users IS 'Auth: Stores user login data within a secure schema.';


--
-- Name: COLUMN users.is_sso_user; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON COLUMN auth.users.is_sso_user IS 'Auth: Set this column to true when the account comes from SSO. These accounts can have duplicate emails.';


--
-- Name: webauthn_challenges; Type: TABLE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TABLE auth.webauthn_challenges (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    user_id uuid,
    challenge_type text NOT NULL,
    session_data jsonb NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    expires_at timestamp with time zone NOT NULL,
    CONSTRAINT webauthn_challenges_challenge_type_check CHECK ((challenge_type = ANY (ARRAY['signup'::text, 'registration'::text, 'authentication'::text])))
);


ALTER TABLE auth.webauthn_challenges OWNER TO supabase_auth_admin;

--
-- Name: webauthn_credentials; Type: TABLE; Schema: auth; Owner: supabase_auth_admin
--

CREATE TABLE auth.webauthn_credentials (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    user_id uuid NOT NULL,
    credential_id bytea NOT NULL,
    public_key bytea NOT NULL,
    attestation_type text DEFAULT ''::text NOT NULL,
    aaguid uuid,
    sign_count bigint DEFAULT 0 NOT NULL,
    transports jsonb DEFAULT '[]'::jsonb NOT NULL,
    backup_eligible boolean DEFAULT false NOT NULL,
    backed_up boolean DEFAULT false NOT NULL,
    friendly_name text DEFAULT ''::text NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    last_used_at timestamp with time zone
);


ALTER TABLE auth.webauthn_credentials OWNER TO supabase_auth_admin;

--
-- Name: activity_calendars; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.activity_calendars (
    id bigint NOT NULL,
    document_id bigint NOT NULL,
    academic_year character varying(255) NOT NULL,
    term character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.activity_calendars OWNER TO postgres;

--
-- Name: activity_calendars_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.activity_calendars_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.activity_calendars_id_seq OWNER TO postgres;

--
-- Name: activity_calendars_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.activity_calendars_id_seq OWNED BY public.activity_calendars.id;


--
-- Name: activity_proposals; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.activity_proposals (
    id bigint NOT NULL,
    document_id bigint NOT NULL,
    calendar_mode character varying(255) NOT NULL,
    calendar_activity_id bigint,
    title character varying(255) NOT NULL,
    objectives text,
    narrative text,
    proposed_budget numeric(10,2),
    form_step smallint DEFAULT '2'::smallint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    activity_nature character varying(255),
    activity_type character varying(255),
    partner_organizations json,
    target_sdg character varying(255),
    budget_source character varying(255),
    criteria_mechanics text,
    program_flow text,
    source_of_funding text,
    expenses text,
    expense_items json
);


ALTER TABLE public.activity_proposals OWNER TO postgres;

--
-- Name: activity_proposals_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.activity_proposals_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.activity_proposals_id_seq OWNER TO postgres;

--
-- Name: activity_proposals_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.activity_proposals_id_seq OWNED BY public.activity_proposals.id;


--
-- Name: after_activity_reports; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.after_activity_reports (
    id bigint NOT NULL,
    document_id bigint NOT NULL,
    activity_proposal_id bigint NOT NULL,
    summary text NOT NULL,
    outcomes text,
    participant_count integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    activity_chairs json,
    prepared_by character varying(255),
    event_program text,
    target_participants_percentage smallint
);


ALTER TABLE public.after_activity_reports OWNER TO postgres;

--
-- Name: after_activity_reports_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.after_activity_reports_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.after_activity_reports_id_seq OWNER TO postgres;

--
-- Name: after_activity_reports_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.after_activity_reports_id_seq OWNED BY public.after_activity_reports.id;


--
-- Name: approval_notifications; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.approval_notifications (
    id bigint NOT NULL,
    document_id bigint NOT NULL,
    user_id bigint NOT NULL,
    step_position integer NOT NULL,
    created_at timestamp(0) without time zone NOT NULL
);


ALTER TABLE public.approval_notifications OWNER TO postgres;

--
-- Name: approval_notifications_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.approval_notifications_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.approval_notifications_id_seq OWNER TO postgres;

--
-- Name: approval_notifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.approval_notifications_id_seq OWNED BY public.approval_notifications.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


ALTER TABLE public.cache OWNER TO postgres;

--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


ALTER TABLE public.cache_locks OWNER TO postgres;

--
-- Name: calendar_activities; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.calendar_activities (
    id bigint NOT NULL,
    activity_calendar_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    venue character varying(255) NOT NULL,
    activity_date date NOT NULL,
    start_time time(0) without time zone NOT NULL,
    end_time time(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    sdg character varying(255),
    participant_program_assigned character varying(255),
    budget numeric(10,2)
);


ALTER TABLE public.calendar_activities OWNER TO postgres;

--
-- Name: calendar_activities_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.calendar_activities_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.calendar_activities_id_seq OWNER TO postgres;

--
-- Name: calendar_activities_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.calendar_activities_id_seq OWNED BY public.calendar_activities.id;


--
-- Name: document_attachments; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.document_attachments (
    id bigint NOT NULL,
    document_id bigint NOT NULL,
    slot_key character varying(255) NOT NULL,
    original_filename character varying(255) NOT NULL,
    path character varying(255) NOT NULL,
    disk character varying(255) NOT NULL,
    mime_type character varying(255) NOT NULL,
    size bigint,
    uploaded_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.document_attachments OWNER TO postgres;

--
-- Name: document_attachments_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.document_attachments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.document_attachments_id_seq OWNER TO postgres;

--
-- Name: document_attachments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.document_attachments_id_seq OWNED BY public.document_attachments.id;


--
-- Name: document_step_approvals; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.document_step_approvals (
    id bigint NOT NULL,
    document_id bigint NOT NULL,
    workflow_step_id bigint NOT NULL,
    step_position integer NOT NULL,
    user_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.document_step_approvals OWNER TO postgres;

--
-- Name: document_step_approvals_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.document_step_approvals_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.document_step_approvals_id_seq OWNER TO postgres;

--
-- Name: document_step_approvals_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.document_step_approvals_id_seq OWNED BY public.document_step_approvals.id;


--
-- Name: document_transitions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.document_transitions (
    id bigint NOT NULL,
    document_id bigint NOT NULL,
    actor_id bigint,
    action character varying(255) NOT NULL,
    from_status character varying(255),
    to_status character varying(255) NOT NULL,
    step_position integer,
    comment text,
    created_at timestamp(0) without time zone NOT NULL,
    flagged_sections json,
    section_comments json,
    field_changes json
);


ALTER TABLE public.document_transitions OWNER TO postgres;

--
-- Name: document_transitions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.document_transitions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.document_transitions_id_seq OWNER TO postgres;

--
-- Name: document_transitions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.document_transitions_id_seq OWNED BY public.document_transitions.id;


--
-- Name: documents; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.documents (
    id bigint NOT NULL,
    form_type character varying(255) NOT NULL,
    variant character varying(255),
    title character varying(255) NOT NULL,
    status character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    current_step_position integer,
    organization_id bigint NOT NULL,
    workflow_template_id bigint,
    submitted_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.documents OWNER TO postgres;

--
-- Name: documents_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.documents_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.documents_id_seq OWNER TO postgres;

--
-- Name: documents_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.documents_id_seq OWNED BY public.documents.id;


--
-- Name: email_verification_codes; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.email_verification_codes (
    id bigint NOT NULL,
    email character varying(255) NOT NULL,
    purpose character varying(255) NOT NULL,
    code_hash character varying(255) NOT NULL,
    payload text,
    user_id bigint,
    attempts smallint DEFAULT '0'::smallint NOT NULL,
    locked_until timestamp(0) without time zone,
    expires_at timestamp(0) without time zone NOT NULL,
    consumed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.email_verification_codes OWNER TO postgres;

--
-- Name: email_verification_codes_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.email_verification_codes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.email_verification_codes_id_seq OWNER TO postgres;

--
-- Name: email_verification_codes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.email_verification_codes_id_seq OWNED BY public.email_verification_codes.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection character varying(255) NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.failed_jobs OWNER TO postgres;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.failed_jobs_id_seq OWNER TO postgres;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


ALTER TABLE public.job_batches OWNER TO postgres;

--
-- Name: jobs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


ALTER TABLE public.jobs OWNER TO postgres;

--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.jobs_id_seq OWNER TO postgres;

--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


ALTER TABLE public.migrations OWNER TO postgres;

--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.migrations_id_seq OWNER TO postgres;

--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: notifications; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.notifications (
    id uuid NOT NULL,
    type character varying(255) NOT NULL,
    notifiable_type character varying(255) NOT NULL,
    notifiable_id bigint NOT NULL,
    data text NOT NULL,
    read_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.notifications OWNER TO postgres;

--
-- Name: organization_join_requests; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.organization_join_requests (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    organization_id bigint NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    decided_by bigint,
    decided_at timestamp(0) without time zone,
    decision_comment text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.organization_join_requests OWNER TO postgres;

--
-- Name: organization_join_requests_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.organization_join_requests_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.organization_join_requests_id_seq OWNER TO postgres;

--
-- Name: organization_join_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.organization_join_requests_id_seq OWNED BY public.organization_join_requests.id;


--
-- Name: organization_memberships; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.organization_memberships (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    organization_id bigint NOT NULL,
    "position" character varying(255) NOT NULL,
    academic_year character varying(255) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.organization_memberships OWNER TO postgres;

--
-- Name: organization_memberships_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.organization_memberships_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.organization_memberships_id_seq OWNER TO postgres;

--
-- Name: organization_memberships_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.organization_memberships_id_seq OWNED BY public.organization_memberships.id;


--
-- Name: organization_registration_details; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.organization_registration_details (
    id bigint NOT NULL,
    document_id bigint NOT NULL,
    organization_type character varying(255) NOT NULL,
    purpose_of_organization text NOT NULL,
    contact_person character varying(255) NOT NULL,
    contact_no character varying(255) NOT NULL,
    email_address character varying(255) NOT NULL,
    date_organized date NOT NULL,
    adviser_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    academic_year character varying(255),
    term character varying(255),
    covers_academic_year character varying(255)
);


ALTER TABLE public.organization_registration_details OWNER TO postgres;

--
-- Name: organization_registration_details_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.organization_registration_details_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.organization_registration_details_id_seq OWNER TO postgres;

--
-- Name: organization_registration_details_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.organization_registration_details_id_seq OWNED BY public.organization_registration_details.id;


--
-- Name: organizations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.organizations (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    school_id bigint,
    program_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.organizations OWNER TO postgres;

--
-- Name: organizations_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.organizations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.organizations_id_seq OWNER TO postgres;

--
-- Name: organizations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.organizations_id_seq OWNED BY public.organizations.id;


--
-- Name: passkeys; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.passkeys (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    credential_id character varying(255) NOT NULL,
    credential json NOT NULL,
    last_used_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.passkeys OWNER TO postgres;

--
-- Name: passkeys_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.passkeys_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.passkeys_id_seq OWNER TO postgres;

--
-- Name: passkeys_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.passkeys_id_seq OWNED BY public.passkeys.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


ALTER TABLE public.password_reset_tokens OWNER TO postgres;

--
-- Name: programs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.programs (
    id bigint NOT NULL,
    school_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.programs OWNER TO postgres;

--
-- Name: programs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.programs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.programs_id_seq OWNER TO postgres;

--
-- Name: programs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.programs_id_seq OWNED BY public.programs.id;


--
-- Name: role_assignments; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.role_assignments (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    role character varying(255) NOT NULL,
    school_id bigint,
    program_id bigint,
    organization_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.role_assignments OWNER TO postgres;

--
-- Name: role_assignments_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.role_assignments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.role_assignments_id_seq OWNER TO postgres;

--
-- Name: role_assignments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.role_assignments_id_seq OWNED BY public.role_assignments.id;


--
-- Name: schools; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.schools (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    type character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.schools OWNER TO postgres;

--
-- Name: schools_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.schools_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.schools_id_seq OWNER TO postgres;

--
-- Name: schools_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.schools_id_seq OWNED BY public.schools.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


ALTER TABLE public.sessions OWNER TO postgres;

--
-- Name: settings; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.settings (
    id bigint NOT NULL,
    key character varying(255) NOT NULL,
    value character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.settings OWNER TO postgres;

--
-- Name: settings_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.settings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.settings_id_seq OWNER TO postgres;

--
-- Name: settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.settings_id_seq OWNED BY public.settings.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    two_factor_secret text,
    two_factor_recovery_codes text,
    two_factor_confirmed_at timestamp(0) without time zone,
    account_status character varying(255) DEFAULT 'unverified'::character varying NOT NULL,
    id_number character varying(255)
);


ALTER TABLE public.users OWNER TO postgres;

--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO postgres;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: workflow_steps; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.workflow_steps (
    id bigint NOT NULL,
    workflow_template_id bigint NOT NULL,
    "position" integer NOT NULL,
    role character varying(255) NOT NULL,
    required_approvals smallint DEFAULT '1'::smallint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.workflow_steps OWNER TO postgres;

--
-- Name: workflow_steps_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.workflow_steps_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.workflow_steps_id_seq OWNER TO postgres;

--
-- Name: workflow_steps_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.workflow_steps_id_seq OWNED BY public.workflow_steps.id;


--
-- Name: workflow_templates; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.workflow_templates (
    id bigint NOT NULL,
    form_type character varying(255) NOT NULL,
    variant character varying(255),
    name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.workflow_templates OWNER TO postgres;

--
-- Name: workflow_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.workflow_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.workflow_templates_id_seq OWNER TO postgres;

--
-- Name: workflow_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.workflow_templates_id_seq OWNED BY public.workflow_templates.id;


--
-- Name: messages; Type: TABLE; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE TABLE realtime.messages (
    topic text NOT NULL,
    extension text NOT NULL,
    payload jsonb,
    event text,
    private boolean DEFAULT false,
    updated_at timestamp without time zone DEFAULT now() NOT NULL,
    inserted_at timestamp without time zone DEFAULT now() NOT NULL,
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    binary_payload bytea,
    skip_broadcast boolean DEFAULT false NOT NULL
)
PARTITION BY RANGE (inserted_at);


ALTER TABLE realtime.messages OWNER TO supabase_realtime_admin;

--
-- Name: schema_migrations; Type: TABLE; Schema: realtime; Owner: supabase_admin
--

CREATE TABLE realtime.schema_migrations (
    version bigint NOT NULL,
    inserted_at timestamp(0) without time zone DEFAULT now()
);


ALTER TABLE realtime.schema_migrations OWNER TO supabase_admin;

--
-- Name: subscription; Type: TABLE; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE TABLE realtime.subscription (
    id bigint NOT NULL,
    subscription_id uuid NOT NULL,
    entity regclass NOT NULL,
    filters realtime.user_defined_filter[] DEFAULT '{}'::realtime.user_defined_filter[] NOT NULL,
    claims jsonb NOT NULL,
    claims_role regrole GENERATED ALWAYS AS (realtime.to_regrole((claims ->> 'role'::text))) STORED NOT NULL,
    created_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL,
    action_filter text DEFAULT '*'::text,
    selected_columns text[],
    CONSTRAINT subscription_action_filter_check CHECK ((action_filter = ANY (ARRAY['*'::text, 'INSERT'::text, 'UPDATE'::text, 'DELETE'::text])))
);


ALTER TABLE realtime.subscription OWNER TO supabase_realtime_admin;

--
-- Name: subscription_id_seq; Type: SEQUENCE; Schema: realtime; Owner: supabase_realtime_admin
--

ALTER TABLE realtime.subscription ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME realtime.subscription_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: buckets; Type: TABLE; Schema: storage; Owner: supabase_storage_admin
--

CREATE TABLE storage.buckets (
    id text NOT NULL,
    name text NOT NULL,
    owner uuid,
    created_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now(),
    public boolean DEFAULT false,
    avif_autodetection boolean DEFAULT false,
    file_size_limit bigint,
    allowed_mime_types text[],
    owner_id text,
    type storage.buckettype DEFAULT 'STANDARD'::storage.buckettype NOT NULL,
    versioning_status text DEFAULT 'DISABLED'::text NOT NULL,
    CONSTRAINT buckets_versioning_dark_check CHECK ((versioning_status = 'DISABLED'::text)),
    CONSTRAINT buckets_versioning_standard_only_check CHECK (((type = 'STANDARD'::storage.buckettype) OR (versioning_status = 'DISABLED'::text))),
    CONSTRAINT buckets_versioning_status_check CHECK ((versioning_status = ANY (ARRAY['DISABLED'::text, 'ENABLED'::text, 'SUSPENDED'::text])))
);


ALTER TABLE storage.buckets OWNER TO supabase_storage_admin;

--
-- Name: COLUMN buckets.owner; Type: COMMENT; Schema: storage; Owner: supabase_storage_admin
--

COMMENT ON COLUMN storage.buckets.owner IS 'Field is deprecated, use owner_id instead';


--
-- Name: buckets_analytics; Type: TABLE; Schema: storage; Owner: supabase_storage_admin
--

CREATE TABLE storage.buckets_analytics (
    name text NOT NULL,
    type storage.buckettype DEFAULT 'ANALYTICS'::storage.buckettype NOT NULL,
    format text DEFAULT 'ICEBERG'::text NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    deleted_at timestamp with time zone
);


ALTER TABLE storage.buckets_analytics OWNER TO supabase_storage_admin;

--
-- Name: buckets_vectors; Type: TABLE; Schema: storage; Owner: supabase_storage_admin
--

CREATE TABLE storage.buckets_vectors (
    id text NOT NULL,
    type storage.buckettype DEFAULT 'VECTOR'::storage.buckettype NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE storage.buckets_vectors OWNER TO supabase_storage_admin;

--
-- Name: migrations; Type: TABLE; Schema: storage; Owner: supabase_storage_admin
--

CREATE TABLE storage.migrations (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    hash character varying(40) NOT NULL,
    executed_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE storage.migrations OWNER TO supabase_storage_admin;

--
-- Name: objects; Type: TABLE; Schema: storage; Owner: supabase_storage_admin
--

CREATE TABLE storage.objects (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    bucket_id text,
    name text,
    owner uuid,
    created_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now(),
    last_accessed_at timestamp with time zone DEFAULT now(),
    metadata jsonb,
    path_tokens text[] GENERATED ALWAYS AS (string_to_array(name, '/'::text)) STORED,
    version text,
    owner_id text,
    user_metadata jsonb,
    archived_at timestamp with time zone,
    is_delete_marker boolean DEFAULT false NOT NULL,
    is_versioned boolean DEFAULT false NOT NULL
);


ALTER TABLE storage.objects OWNER TO supabase_storage_admin;

--
-- Name: COLUMN objects.owner; Type: COMMENT; Schema: storage; Owner: supabase_storage_admin
--

COMMENT ON COLUMN storage.objects.owner IS 'Field is deprecated, use owner_id instead';


--
-- Name: s3_multipart_uploads; Type: TABLE; Schema: storage; Owner: supabase_storage_admin
--

CREATE TABLE storage.s3_multipart_uploads (
    id text NOT NULL,
    in_progress_size bigint DEFAULT 0 NOT NULL,
    upload_signature text NOT NULL,
    bucket_id text NOT NULL,
    key text NOT NULL COLLATE pg_catalog."C",
    version text NOT NULL,
    owner_id text,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    user_metadata jsonb,
    metadata jsonb
);


ALTER TABLE storage.s3_multipart_uploads OWNER TO supabase_storage_admin;

--
-- Name: s3_multipart_uploads_parts; Type: TABLE; Schema: storage; Owner: supabase_storage_admin
--

CREATE TABLE storage.s3_multipart_uploads_parts (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    upload_id text NOT NULL,
    size bigint DEFAULT 0 NOT NULL,
    part_number integer NOT NULL,
    bucket_id text NOT NULL,
    key text NOT NULL COLLATE pg_catalog."C",
    etag text NOT NULL,
    owner_id text,
    version text NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE storage.s3_multipart_uploads_parts OWNER TO supabase_storage_admin;

--
-- Name: vector_indexes; Type: TABLE; Schema: storage; Owner: supabase_storage_admin
--

CREATE TABLE storage.vector_indexes (
    id text DEFAULT gen_random_uuid() NOT NULL,
    name text NOT NULL COLLATE pg_catalog."C",
    bucket_id text NOT NULL,
    data_type text NOT NULL,
    dimension integer NOT NULL,
    distance_metric text NOT NULL,
    metadata_configuration jsonb,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE storage.vector_indexes OWNER TO supabase_storage_admin;

--
-- Name: refresh_tokens id; Type: DEFAULT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.refresh_tokens ALTER COLUMN id SET DEFAULT nextval('auth.refresh_tokens_id_seq'::regclass);


--
-- Name: activity_calendars id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.activity_calendars ALTER COLUMN id SET DEFAULT nextval('public.activity_calendars_id_seq'::regclass);


--
-- Name: activity_proposals id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.activity_proposals ALTER COLUMN id SET DEFAULT nextval('public.activity_proposals_id_seq'::regclass);


--
-- Name: after_activity_reports id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.after_activity_reports ALTER COLUMN id SET DEFAULT nextval('public.after_activity_reports_id_seq'::regclass);


--
-- Name: approval_notifications id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.approval_notifications ALTER COLUMN id SET DEFAULT nextval('public.approval_notifications_id_seq'::regclass);


--
-- Name: calendar_activities id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.calendar_activities ALTER COLUMN id SET DEFAULT nextval('public.calendar_activities_id_seq'::regclass);


--
-- Name: document_attachments id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.document_attachments ALTER COLUMN id SET DEFAULT nextval('public.document_attachments_id_seq'::regclass);


--
-- Name: document_step_approvals id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.document_step_approvals ALTER COLUMN id SET DEFAULT nextval('public.document_step_approvals_id_seq'::regclass);


--
-- Name: document_transitions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.document_transitions ALTER COLUMN id SET DEFAULT nextval('public.document_transitions_id_seq'::regclass);


--
-- Name: documents id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.documents ALTER COLUMN id SET DEFAULT nextval('public.documents_id_seq'::regclass);


--
-- Name: email_verification_codes id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.email_verification_codes ALTER COLUMN id SET DEFAULT nextval('public.email_verification_codes_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: organization_join_requests id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.organization_join_requests ALTER COLUMN id SET DEFAULT nextval('public.organization_join_requests_id_seq'::regclass);


--
-- Name: organization_memberships id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.organization_memberships ALTER COLUMN id SET DEFAULT nextval('public.organization_memberships_id_seq'::regclass);


--
-- Name: organization_registration_details id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.organization_registration_details ALTER COLUMN id SET DEFAULT nextval('public.organization_registration_details_id_seq'::regclass);


--
-- Name: organizations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.organizations ALTER COLUMN id SET DEFAULT nextval('public.organizations_id_seq'::regclass);


--
-- Name: passkeys id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.passkeys ALTER COLUMN id SET DEFAULT nextval('public.passkeys_id_seq'::regclass);


--
-- Name: programs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.programs ALTER COLUMN id SET DEFAULT nextval('public.programs_id_seq'::regclass);


--
-- Name: role_assignments id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.role_assignments ALTER COLUMN id SET DEFAULT nextval('public.role_assignments_id_seq'::regclass);


--
-- Name: schools id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.schools ALTER COLUMN id SET DEFAULT nextval('public.schools_id_seq'::regclass);


--
-- Name: settings id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.settings ALTER COLUMN id SET DEFAULT nextval('public.settings_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: workflow_steps id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.workflow_steps ALTER COLUMN id SET DEFAULT nextval('public.workflow_steps_id_seq'::regclass);


--
-- Name: workflow_templates id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.workflow_templates ALTER COLUMN id SET DEFAULT nextval('public.workflow_templates_id_seq'::regclass);


--
-- Data for Name: audit_log_entries; Type: TABLE DATA; Schema: auth; Owner: supabase_auth_admin
--

COPY auth.audit_log_entries (instance_id, id, payload, created_at, ip_address) FROM stdin;
\.


--
-- Data for Name: custom_oauth_providers; Type: TABLE DATA; Schema: auth; Owner: supabase_auth_admin
--

COPY auth.custom_oauth_providers (id, provider_type, identifier, name, client_id, client_secret, acceptable_client_ids, scopes, pkce_enabled, attribute_mapping, authorization_params, enabled, email_optional, issuer, discovery_url, skip_nonce_check, cached_discovery, discovery_cached_at, authorization_url, token_url, userinfo_url, jwks_uri, created_at, updated_at, custom_claims_allowlist) FROM stdin;
\.


--
-- Data for Name: flow_state; Type: TABLE DATA; Schema: auth; Owner: supabase_auth_admin
--

COPY auth.flow_state (id, user_id, auth_code, code_challenge_method, code_challenge, provider_type, provider_access_token, provider_refresh_token, created_at, updated_at, authentication_method, auth_code_issued_at, invite_token, referrer, oauth_client_state_id, linking_target_id, email_optional) FROM stdin;
\.


--
-- Data for Name: identities; Type: TABLE DATA; Schema: auth; Owner: supabase_auth_admin
--

COPY auth.identities (provider_id, user_id, identity_data, provider, last_sign_in_at, created_at, updated_at, id) FROM stdin;
\.


--
-- Data for Name: instances; Type: TABLE DATA; Schema: auth; Owner: supabase_auth_admin
--

COPY auth.instances (id, uuid, raw_base_config, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: mfa_amr_claims; Type: TABLE DATA; Schema: auth; Owner: supabase_auth_admin
--

COPY auth.mfa_amr_claims (session_id, created_at, updated_at, authentication_method, id) FROM stdin;
\.


--
-- Data for Name: mfa_challenges; Type: TABLE DATA; Schema: auth; Owner: supabase_auth_admin
--

COPY auth.mfa_challenges (id, factor_id, created_at, verified_at, ip_address, otp_code, web_authn_session_data) FROM stdin;
\.


--
-- Data for Name: mfa_factors; Type: TABLE DATA; Schema: auth; Owner: supabase_auth_admin
--

COPY auth.mfa_factors (id, user_id, friendly_name, factor_type, status, created_at, updated_at, secret, phone, last_challenged_at, web_authn_credential, web_authn_aaguid, last_webauthn_challenge_data) FROM stdin;
\.


--
-- Data for Name: oauth_authorizations; Type: TABLE DATA; Schema: auth; Owner: supabase_auth_admin
--

COPY auth.oauth_authorizations (id, authorization_id, client_id, user_id, redirect_uri, scope, state, resource, code_challenge, code_challenge_method, response_type, status, authorization_code, created_at, expires_at, approved_at, nonce) FROM stdin;
\.


--
-- Data for Name: oauth_client_states; Type: TABLE DATA; Schema: auth; Owner: supabase_auth_admin
--

COPY auth.oauth_client_states (id, provider_type, code_verifier, created_at) FROM stdin;
\.


--
-- Data for Name: oauth_clients; Type: TABLE DATA; Schema: auth; Owner: supabase_auth_admin
--

COPY auth.oauth_clients (id, client_secret_hash, registration_type, redirect_uris, grant_types, client_name, client_uri, logo_uri, created_at, updated_at, deleted_at, client_type, token_endpoint_auth_method) FROM stdin;
\.


--
-- Data for Name: oauth_consents; Type: TABLE DATA; Schema: auth; Owner: supabase_auth_admin
--

COPY auth.oauth_consents (id, user_id, client_id, scopes, granted_at, revoked_at) FROM stdin;
\.


--
-- Data for Name: one_time_tokens; Type: TABLE DATA; Schema: auth; Owner: supabase_auth_admin
--

COPY auth.one_time_tokens (id, user_id, token_type, token_hash, relates_to, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: refresh_tokens; Type: TABLE DATA; Schema: auth; Owner: supabase_auth_admin
--

COPY auth.refresh_tokens (instance_id, id, token, user_id, revoked, created_at, updated_at, parent, session_id) FROM stdin;
\.


--
-- Data for Name: saml_providers; Type: TABLE DATA; Schema: auth; Owner: supabase_auth_admin
--

COPY auth.saml_providers (id, sso_provider_id, entity_id, metadata_xml, metadata_url, attribute_mapping, created_at, updated_at, name_id_format) FROM stdin;
\.


--
-- Data for Name: saml_relay_states; Type: TABLE DATA; Schema: auth; Owner: supabase_auth_admin
--

COPY auth.saml_relay_states (id, sso_provider_id, request_id, for_email, redirect_to, created_at, updated_at, flow_state_id) FROM stdin;
\.


--
-- Data for Name: schema_migrations; Type: TABLE DATA; Schema: auth; Owner: supabase_auth_admin
--

COPY auth.schema_migrations (version) FROM stdin;
20171026211738
20171026211808
20171026211834
20180103212743
20180108183307
20180119214651
20180125194653
00
20210710035447
20210722035447
20210730183235
20210909172000
20210927181326
20211122151130
20211124214934
20211202183645
20220114185221
20220114185340
20220224000811
20220323170000
20220429102000
20220531120530
20220614074223
20220811173540
20221003041349
20221003041400
20221011041400
20221020193600
20221021073300
20221021082433
20221027105023
20221114143122
20221114143410
20221125140132
20221208132122
20221215195500
20221215195800
20221215195900
20230116124310
20230116124412
20230131181311
20230322519590
20230402418590
20230411005111
20230508135423
20230523124323
20230818113222
20230914180801
20231027141322
20231114161723
20231117164230
20240115144230
20240214120130
20240306115329
20240314092811
20240427152123
20240612123726
20240729123726
20240802193726
20240806073726
20241009103726
20250717082212
20250731150234
20250804100000
20250901200500
20250903112500
20250904133000
20250925093508
20251007112900
20251104100000
20251111201300
20251201000000
20260115000000
20260121000000
20260219120000
20260302000000
20260625000000
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: auth; Owner: supabase_auth_admin
--

COPY auth.sessions (id, user_id, created_at, updated_at, factor_id, aal, not_after, refreshed_at, user_agent, ip, tag, oauth_client_id, refresh_token_hmac_key, refresh_token_counter, scopes) FROM stdin;
\.


--
-- Data for Name: sso_domains; Type: TABLE DATA; Schema: auth; Owner: supabase_auth_admin
--

COPY auth.sso_domains (id, sso_provider_id, domain, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: sso_providers; Type: TABLE DATA; Schema: auth; Owner: supabase_auth_admin
--

COPY auth.sso_providers (id, resource_id, created_at, updated_at, disabled) FROM stdin;
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: auth; Owner: supabase_auth_admin
--

COPY auth.users (instance_id, id, aud, role, email, encrypted_password, email_confirmed_at, invited_at, confirmation_token, confirmation_sent_at, recovery_token, recovery_sent_at, email_change_token_new, email_change, email_change_sent_at, last_sign_in_at, raw_app_meta_data, raw_user_meta_data, is_super_admin, created_at, updated_at, phone, phone_confirmed_at, phone_change, phone_change_token, phone_change_sent_at, email_change_token_current, email_change_confirm_status, banned_until, reauthentication_token, reauthentication_sent_at, is_sso_user, deleted_at, is_anonymous) FROM stdin;
\.


--
-- Data for Name: webauthn_challenges; Type: TABLE DATA; Schema: auth; Owner: supabase_auth_admin
--

COPY auth.webauthn_challenges (id, user_id, challenge_type, session_data, created_at, expires_at) FROM stdin;
\.


--
-- Data for Name: webauthn_credentials; Type: TABLE DATA; Schema: auth; Owner: supabase_auth_admin
--

COPY auth.webauthn_credentials (id, user_id, credential_id, public_key, attestation_type, aaguid, sign_count, transports, backup_eligible, backed_up, friendly_name, created_at, updated_at, last_used_at) FROM stdin;
\.


--
-- Data for Name: activity_calendars; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.activity_calendars (id, document_id, academic_year, term, created_at, updated_at) FROM stdin;
12	52	2026-2027	first_term	2026-08-21 05:51:56	2026-08-21 05:51:56
13	53	2026-2027	first_term	2026-08-21 05:53:06	2026-08-21 05:53:06
14	54	2026-2027	first_term	2026-08-21 05:54:09	2026-08-21 05:54:09
15	55	2026-2027	first_term	2026-08-21 05:55:18	2026-08-21 05:55:18
16	56	2026-2027	first_term	2026-08-21 05:56:00	2026-08-21 05:56:00
17	57	2026-2027	first_term	2026-08-21 05:57:04	2026-08-21 05:57:04
18	58	2026-2027	first_term	2026-08-21 05:58:12	2026-08-21 05:58:12
19	59	2026-2027	first_term	2026-08-21 05:59:16	2026-08-21 05:59:16
20	61	2026-2027	first_term	2026-08-21 06:00:24	2026-08-21 06:00:24
21	64	2026-2027	first_term	2026-08-21 06:05:25	2026-08-21 06:05:25
22	66	2026-2027	first_term	2026-08-21 06:09:55	2026-08-21 06:09:55
23	70	2026-2027	first_term	2026-08-23 12:53:40	2026-08-23 12:53:40
24	75	2026-2027	first_term	2026-08-24 12:25:51	2026-08-24 12:25:51
25	76	2026-2027	first_term	2026-08-24 12:42:38	2026-08-24 12:42:38
26	79	2026-2027	first_term	2026-08-24 13:52:34	2026-08-24 13:52:34
27	93	2026-2027	first_term	2026-09-03 08:20:31	2026-09-03 08:20:31
28	94	2026-2027	first_term	2026-09-03 08:22:06	2026-09-03 08:22:06
29	95	2026-2027	first_term	2026-09-03 08:27:22	2026-09-03 08:27:22
30	96	2026-2027	first_term	2026-09-03 08:29:50	2026-09-03 08:29:50
31	97	2026-2027	first_term	2026-09-03 08:36:44	2026-09-03 08:36:44
32	98	2026-2027	first_term	2026-09-03 09:01:33	2026-09-03 09:01:33
33	99	2026-2027	first_term	2026-09-03 09:04:24	2026-09-03 09:04:24
34	100	2026-2027	first_term	2026-09-03 09:07:25	2026-09-03 09:07:25
35	101	2026-2027	first_term	2026-09-03 09:08:31	2026-09-03 09:08:31
\.


--
-- Data for Name: activity_proposals; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.activity_proposals (id, document_id, calendar_mode, calendar_activity_id, title, objectives, narrative, proposed_budget, form_step, created_at, updated_at, activity_nature, activity_type, partner_organizations, target_sdg, budget_source, criteria_mechanics, program_flow, source_of_funding, expenses, expense_items) FROM stdin;
17	79	off_calendar	45	Student Leadership Workshop	To train student leaders in communication, teamwork, and decision‑making skills.	This workshop will gather student officers and aspiring leaders to learn practical leadership strategies. It includes talks, group activities, and reflection sessions.	5000.00	2	2026-08-24 13:52:34	2026-08-24 13:54:48	non_curricular	seminar_workshop	["Student Council + Guidance Office"]	quality_education	Org funds + small sponsorship	Open to all recognized student organizations. Participants must register in advance. Attendance will be monitored for certification.	8:30 AM – Registration\n\n9:00 AM – Opening remarks\n\n9:15 AM – Keynote lecture on leadership\n\n10:00 AM – Group activity: problem‑solving exercise\n\n11:00 AM – Sharing and reflection\n\n11:45 AM – Closing remarks\n\n12:00 PM – End	Organization funds + small sponsorship.”	\N	[{"label":"Venue rental","amount":"2000"},{"label":"Resource person honorarium","amount":"2000"},{"label":"Snacks","amount":"1000"}]
16	78	on_calendar	42	Pakain Program	To provide free meals for students and promote camaraderie during the school activity.	The program will serve food to all participants after the main school event. It aims to support student well‑being and encourage community spirit.	7000.00	2	2026-08-24 13:02:21	2026-08-24 13:06:33	non_curricular	others	["NUD"]	zero_hunger	Sponsorship	Meals will be distributed fairly to all registered attendees. Each participant will receive one food pack. Volunteers will help with distribution.	9:00 AM – Preparation of food packs\n\n10:00 AM – Start of distribution\n\n10:30 AM – Lunch fellowship\n\n11:00 AM – Closing and clean‑up	Organization funds and sponsorships.	\N	[{"label":"Food ingredients","amount":"5000"},{"label":"Utensils\\/packaging","amount":"1000"},{"label":"Drinks","amount":"1500"}]
18	80	on_calendar	32	Accountancy Career Talk	In addition to the original goals, the activity now aims to broaden student perspectives by exposing them to diverse career paths in accountancy, strengthen their confidence in pursuing professional examinations, and foster meaningful connections with industry practitioners. These new objectives emphasize practical readiness, career awareness, and networking opportunities, ensuring that the event delivers both academic and professional value to participants.	The career talk will feature guest speakers from accounting firms and alumni sharing their experiences. It aims to help students prepare for professional exams and explore job opportunities.	5000.00	2	2026-08-24 16:20:19	2026-08-24 16:37:10	co_curricular	seminar_workshop	["Student Council + Guidance Office"]	quality_education	Org funds + small sponsorship	Open to all JPIA members. Participants must register online. Attendance will be recorded for certificates.	1:00 PM – Opening remarks\n\n1:15 PM – Speaker 1: Auditing career insights\n\n1:45 PM – Speaker 2: Taxation and compliance\n\n2:15 PM – Q&A session\n\n2:45 PM – Closing remarks\n\n3:00 PM – End	Organization funds + small sponsorship.	\N	[{"label":"Venue setup","amount":"1000"},{"label":"Speaker tokens","amount":"2000"},{"label":"Snacks","amount":"1000"},{"label":"Printing of certificates","amount":"1000"}]
8	60	on_calendar	22	Hackathon Kickoff	\N	\N	15000.00	2	2026-08-21 06:00:19	2026-08-21 06:00:19	co_curricular	seminar_workshop	\N	\N	Organization funds	\N	\N	\N	\N	\N
9	61	off_calendar	38	Civil Engineering Site Visit	Expose members to real-world structural engineering practices.	A guided site visit to an active construction project in Batangas, with a Q&A session with the site engineers.	8000.00	2	2026-08-21 06:00:25	2026-08-21 06:00:30	co_curricular	off_campus_activity	\N	\N	Organization funds	\N	\N	\N	\N	\N
10	62	on_calendar	25	Architecture Expo	Showcase student architectural design work to the NU Lipa community.	A campus-wide exhibit of student architectural models and boards, open to all schools.	25000.00	2	2026-08-21 06:01:19	2026-08-21 06:01:23	co_curricular	competition	\N	\N	Organization funds and sponsorships	\N	\N	Organization funds and sponsorships	\N	\N
11	63	on_calendar	37	Valorant Campus Cup	Promote esports as a legitimate co-curricular activity among SHS students.	A campus-wide Valorant tournament open to all SHS student teams, with a small prize pool.	12000.00	2	2026-08-21 06:02:21	2026-08-21 06:02:25	non_curricular	competition	\N	\N	Organization funds	\N	Opening remarks, group stage, playoffs, awarding.	\N	\N	\N
12	64	off_calendar	39	SHS Talent Night	Showcase SHS student talent outside of esports.	An open-mic style talent night for SHS students, off the approved activity calendar.	6000.00	2	2026-08-21 06:05:27	2026-08-21 06:05:32	non_curricular	others	\N	\N	Organization funds	\N	\N	\N	\N	\N
13	65	on_calendar	23	Code Review Bootcamp	Improve code quality practices among CODECS members.	A hands-on bootcamp covering code review etiquette and static analysis tooling.	5000.00	2	2026-08-21 06:06:23	2026-08-21 06:06:27	co_curricular	seminar_workshop	\N	\N	Organization funds	\N	\N	\N	\N	\N
14	66	off_calendar	40	Infrastructure Career Fair	Connect graduating civil engineering students with local firms.	A career fair featuring construction and engineering firms operating in Batangas.	10000.00	2	2026-08-21 06:09:57	2026-08-21 06:10:01	co_curricular	recruitment_audition	\N	\N	Organization funds and partner sponsorships	\N	\N	\N	\N	\N
15	77	on_calendar	42	Pakain Program	\N	\N	7000.00	2	2026-08-24 13:01:05	2026-08-24 13:01:05	non_curricular	donation_drive_fundraising	["NUD"]	zero_hunger	Sponsorship	\N	\N	\N	\N	\N
20	103	on_calendar	57	Sept 9 Act	asd	asd	9000.00	2	2026-09-03 09:29:53	2026-09-03 09:55:57	co_curricular	orientation	["DLSL","CODECS"]	zero_hunger	external	asd	asd	external	\N	[{"label":"1000","amount":"23"},{"label":"2","amount":"23"}]
19	102	on_calendar	57	Sept 9 Act	asd	asd	300000.00	2	2026-09-03 09:11:41	2026-09-05 11:01:33	co_curricular	orientation	["DLSL","CODECS"]	quality_education	external	asdasd	asd	asd	\N	[{"label":"chair","amount":"9999.99"}]
\.


--
-- Data for Name: after_activity_reports; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.after_activity_reports (id, document_id, activity_proposal_id, summary, outcomes, participant_count, created_at, updated_at, activity_chairs, prepared_by, event_program, target_participants_percentage) FROM stdin;
3	67	11	The Valorant Campus Cup drew strong participation across SHS sections and ran without incident.	Increased visibility for esports as a recognized co-curricular activity.	96	2026-08-21 06:13:38	2026-08-21 06:13:38	["Joshua Ramos"]	Joshua Ramos	Group stage, playoffs, awarding ceremony.	90
4	68	13	The Code Review Bootcamp covered static analysis tooling and pairwise review exercises.	Members reported more confidence giving structured code review feedback.	38	2026-08-21 06:14:46	2026-08-21 06:14:46	["Miguel Torres"]	Miguel Torres	Lecture, hands-on exercise, wrap-up discussion.	80
5	69	14	The Infrastructure Career Fair connected students with several partner firms.	Several members received on-the-spot interview invitations.	60	2026-08-21 06:15:34	2026-08-21 06:15:34	["Nathaniel Cruz"]	Nathaniel Cruz	Booth exhibits, employer talks, open networking.	75
6	81	18	okiiiiiii	\N	120	2026-08-24 17:06:06	2026-08-24 17:06:06	["Juan Dela Cruz"]	Maria Santos	okiiiiiiii	80
\.


--
-- Data for Name: approval_notifications; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.approval_notifications (id, document_id, user_id, step_position, created_at) FROM stdin;
139	35	21	1	2026-08-21 05:32:54
140	35	22	1	2026-08-21 05:33:00
141	35	41	1	2026-08-21 05:33:05
142	35	42	1	2026-08-21 05:33:10
143	36	21	1	2026-08-21 05:34:20
144	36	22	1	2026-08-21 05:34:25
145	36	41	1	2026-08-21 05:34:31
146	36	42	1	2026-08-21 05:34:36
147	37	21	1	2026-08-21 05:35:46
148	37	22	1	2026-08-21 05:35:51
149	37	41	1	2026-08-21 05:35:56
150	37	42	1	2026-08-21 05:36:02
151	38	21	1	2026-08-21 05:37:12
152	38	22	1	2026-08-21 05:37:17
153	38	41	1	2026-08-21 05:37:23
154	38	42	1	2026-08-21 05:37:28
155	39	21	1	2026-08-21 05:38:39
156	39	22	1	2026-08-21 05:38:44
157	39	41	1	2026-08-21 05:38:49
158	39	42	1	2026-08-21 05:38:54
159	40	21	1	2026-08-21 05:40:05
160	40	22	1	2026-08-21 05:40:10
161	40	41	1	2026-08-21 05:40:16
162	40	42	1	2026-08-21 05:40:21
163	41	21	1	2026-08-21 05:41:32
164	41	22	1	2026-08-21 05:41:37
165	41	41	1	2026-08-21 05:41:42
166	41	42	1	2026-08-21 05:41:47
167	42	21	1	2026-08-21 05:42:58
168	42	22	1	2026-08-21 05:43:04
169	42	41	1	2026-08-21 05:43:09
170	42	42	1	2026-08-21 05:43:14
171	44	21	1	2026-08-21 05:44:46
172	44	22	1	2026-08-21 05:44:51
173	44	41	1	2026-08-21 05:44:56
174	44	42	1	2026-08-21 05:45:01
175	45	21	1	2026-08-21 05:45:41
176	45	22	1	2026-08-21 05:45:47
177	45	41	1	2026-08-21 05:45:52
178	45	42	1	2026-08-21 05:45:57
179	46	21	1	2026-08-21 05:46:39
180	46	22	1	2026-08-21 05:46:44
181	46	41	1	2026-08-21 05:46:49
182	46	42	1	2026-08-21 05:46:55
183	48	21	1	2026-08-21 05:47:49
184	48	22	1	2026-08-21 05:47:54
185	48	41	1	2026-08-21 05:47:59
186	48	42	1	2026-08-21 05:48:05
187	49	21	1	2026-08-21 05:48:49
188	49	22	1	2026-08-21 05:48:54
189	49	41	1	2026-08-21 05:48:59
190	49	42	1	2026-08-21 05:49:04
191	50	21	1	2026-08-21 05:49:57
192	50	22	1	2026-08-21 05:50:02
193	50	41	1	2026-08-21 05:50:07
194	50	42	1	2026-08-21 05:50:13
195	51	21	1	2026-08-21 05:51:17
196	51	22	1	2026-08-21 05:51:22
197	51	41	1	2026-08-21 05:51:27
198	51	42	1	2026-08-21 05:51:32
199	52	21	1	2026-08-21 05:52:05
200	52	22	1	2026-08-21 05:52:11
201	52	41	1	2026-08-21 05:52:16
202	52	42	1	2026-08-21 05:52:21
203	53	21	1	2026-08-21 05:53:15
204	53	22	1	2026-08-21 05:53:20
205	53	41	1	2026-08-21 05:53:25
206	53	42	1	2026-08-21 05:53:30
207	54	21	1	2026-08-21 05:54:18
208	54	22	1	2026-08-21 05:54:24
209	54	41	1	2026-08-21 05:54:29
210	54	42	1	2026-08-21 05:54:34
211	55	21	1	2026-08-21 05:55:27
212	55	22	1	2026-08-21 05:55:32
213	55	41	1	2026-08-21 05:55:37
214	55	42	1	2026-08-21 05:55:42
215	56	21	1	2026-08-21 05:56:09
216	56	22	1	2026-08-21 05:56:14
217	56	41	1	2026-08-21 05:56:19
218	56	42	1	2026-08-21 05:56:25
219	57	21	1	2026-08-21 05:57:13
220	57	22	1	2026-08-21 05:57:18
221	57	41	1	2026-08-21 05:57:23
222	57	42	1	2026-08-21 05:57:29
223	58	21	1	2026-08-21 05:58:21
224	58	22	1	2026-08-21 05:58:26
225	58	41	1	2026-08-21 05:58:32
226	58	42	1	2026-08-21 05:58:37
227	59	21	1	2026-08-21 05:59:25
228	59	22	1	2026-08-21 05:59:30
229	59	41	1	2026-08-21 05:59:35
230	59	42	1	2026-08-21 05:59:41
231	61	21	1	2026-08-21 06:00:37
232	61	22	1	2026-08-21 06:00:44
233	61	41	1	2026-08-21 06:00:51
234	61	42	1	2026-08-21 06:00:58
235	62	51	1	2026-08-21 06:01:31
236	62	31	2	2026-08-21 06:01:55
237	63	54	1	2026-08-21 06:02:33
238	63	26	2	2026-08-21 06:03:00
239	63	21	3	2026-08-21 06:03:23
240	63	22	3	2026-08-21 06:03:30
241	63	41	3	2026-08-21 06:03:36
242	63	42	3	2026-08-21 06:03:42
243	63	23	4	2026-08-21 06:04:11
244	63	24	5	2026-08-21 06:04:33
245	63	25	6	2026-08-21 06:04:55
246	64	21	1	2026-08-21 06:05:39
247	64	22	1	2026-08-21 06:05:46
248	64	41	1	2026-08-21 06:05:53
249	64	42	1	2026-08-21 06:06:00
250	65	48	1	2026-08-21 06:06:35
251	65	28	2	2026-08-21 06:07:02
252	65	27	3	2026-08-21 06:07:26
253	65	21	4	2026-08-21 06:07:49
254	65	22	4	2026-08-21 06:07:55
255	65	41	4	2026-08-21 06:08:01
256	65	42	4	2026-08-21 06:08:07
257	65	23	5	2026-08-21 06:08:36
258	65	24	6	2026-08-21 06:08:58
259	65	25	7	2026-08-21 06:09:20
260	66	21	1	2026-08-21 06:10:09
261	66	22	1	2026-08-21 06:10:15
262	66	41	1	2026-08-21 06:10:22
263	66	42	1	2026-08-21 06:10:29
264	66	85	2	2026-08-21 06:11:03
265	66	30	3	2026-08-21 06:11:26
266	66	27	4	2026-08-21 06:11:50
267	66	23	5	2026-08-21 06:12:14
268	66	24	6	2026-08-21 06:12:36
269	66	25	7	2026-08-21 06:12:58
270	67	21	1	2026-08-21 06:13:51
271	67	22	1	2026-08-21 06:13:56
272	67	41	1	2026-08-21 06:14:02
273	67	42	1	2026-08-21 06:14:07
274	68	21	1	2026-08-21 06:14:59
275	68	22	1	2026-08-21 06:15:05
276	68	41	1	2026-08-21 06:15:10
277	68	42	1	2026-08-21 06:15:15
278	69	21	1	2026-08-21 06:15:47
279	69	22	1	2026-08-21 06:15:52
280	69	41	1	2026-08-21 06:15:58
281	69	42	1	2026-08-21 06:16:03
282	70	21	1	2026-08-23 12:53:44
283	70	22	1	2026-08-23 12:53:47
284	70	41	1	2026-08-23 12:53:50
285	70	42	1	2026-08-23 12:53:52
286	71	21	1	2026-08-24 05:50:31
287	71	22	1	2026-08-24 05:50:33
288	71	41	1	2026-08-24 05:50:34
289	71	42	1	2026-08-24 05:50:36
290	71	21	1	2026-08-24 05:57:04
291	71	22	1	2026-08-24 05:57:06
292	71	41	1	2026-08-24 05:57:08
293	71	42	1	2026-08-24 05:57:09
294	71	21	1	2026-08-24 06:03:40
295	71	22	1	2026-08-24 06:03:41
296	71	41	1	2026-08-24 06:03:43
297	71	42	1	2026-08-24 06:03:45
298	71	21	1	2026-08-24 06:12:29
299	71	22	1	2026-08-24 06:12:31
300	71	41	1	2026-08-24 06:12:32
301	71	42	1	2026-08-24 06:12:34
302	71	21	1	2026-08-24 06:16:19
303	71	22	1	2026-08-24 06:16:21
304	71	41	1	2026-08-24 06:16:22
305	71	42	1	2026-08-24 06:16:24
306	72	21	1	2026-08-24 06:29:58
307	72	22	1	2026-08-24 06:30:00
308	72	41	1	2026-08-24 06:30:01
309	72	42	1	2026-08-24 06:30:03
310	73	21	1	2026-08-24 08:49:05
311	73	22	1	2026-08-24 08:49:06
312	73	41	1	2026-08-24 08:49:08
313	73	42	1	2026-08-24 08:49:10
314	74	21	1	2026-08-24 09:12:14
315	74	22	1	2026-08-24 09:12:15
316	74	41	1	2026-08-24 09:12:17
317	74	42	1	2026-08-24 09:12:19
318	74	21	1	2026-08-24 09:17:37
319	74	22	1	2026-08-24 09:17:39
320	74	41	1	2026-08-24 09:17:41
321	74	42	1	2026-08-24 09:17:43
322	74	21	1	2026-08-24 09:27:56
323	74	22	1	2026-08-24 09:27:58
324	74	41	1	2026-08-24 09:28:00
325	74	42	1	2026-08-24 09:28:01
326	75	21	1	2026-08-24 12:25:55
327	75	22	1	2026-08-24 12:25:57
328	75	41	1	2026-08-24 12:26:00
329	75	42	1	2026-08-24 12:26:02
330	76	21	1	2026-08-24 12:42:41
331	76	22	1	2026-08-24 12:42:43
332	76	41	1	2026-08-24 12:42:44
333	76	42	1	2026-08-24 12:42:46
334	76	21	1	2026-08-24 12:50:34
335	76	22	1	2026-08-24 12:50:36
336	76	41	1	2026-08-24 12:50:38
337	76	42	1	2026-08-24 12:50:40
338	78	122	1	2026-08-24 13:06:37
339	79	21	1	2026-08-24 13:55:09
340	79	22	1	2026-08-24 13:55:11
341	79	41	1	2026-08-24 13:55:13
342	79	42	1	2026-08-24 13:55:15
343	80	40	1	2026-08-24 16:22:45
344	80	38	2	2026-08-24 16:25:35
345	80	38	2	2026-08-24 16:37:12
346	80	36	3	2026-08-24 16:38:02
347	80	21	4	2026-08-24 16:41:25
348	80	22	4	2026-08-24 16:41:28
349	80	41	4	2026-08-24 16:41:30
350	80	42	4	2026-08-24 16:41:33
351	80	23	5	2026-08-24 16:47:53
352	80	24	6	2026-08-24 16:51:17
353	80	25	7	2026-08-24 16:53:03
354	81	21	1	2026-08-24 17:06:11
355	81	22	1	2026-08-24 17:06:13
356	81	41	1	2026-08-24 17:06:15
357	81	42	1	2026-08-24 17:06:16
358	82	21	1	2026-08-29 14:15:50
359	82	22	1	2026-08-29 14:15:52
360	82	41	1	2026-08-29 14:15:54
361	82	42	1	2026-08-29 14:15:55
362	83	41	1	2026-08-30 02:54:06
363	83	21	1	2026-08-30 02:54:06
364	83	42	1	2026-08-30 02:54:06
365	83	22	1	2026-08-30 02:54:06
366	84	41	1	2026-08-30 03:46:44
367	84	21	1	2026-08-30 03:46:44
368	84	42	1	2026-08-30 03:46:44
369	84	22	1	2026-08-30 03:46:44
370	84	41	1	2026-08-30 03:48:16
371	84	21	1	2026-08-30 03:48:16
372	84	42	1	2026-08-30 03:48:16
373	84	22	1	2026-08-30 03:48:16
374	84	41	1	2026-08-30 03:51:12
375	84	21	1	2026-08-30 03:51:12
376	84	42	1	2026-08-30 03:51:12
377	84	22	1	2026-08-30 03:51:12
378	85	41	1	2026-08-30 04:13:48
379	85	21	1	2026-08-30 04:13:48
380	85	42	1	2026-08-30 04:13:48
381	85	22	1	2026-08-30 04:13:48
382	86	41	1	2026-08-30 04:16:09
383	86	21	1	2026-08-30 04:16:10
384	86	42	1	2026-08-30 04:16:10
385	86	22	1	2026-08-30 04:16:10
386	87	41	1	2026-08-30 08:37:39
387	87	21	1	2026-08-30 08:37:40
388	87	42	1	2026-08-30 08:37:40
389	87	22	1	2026-08-30 08:37:40
390	87	41	1	2026-08-30 08:42:34
391	87	21	1	2026-08-30 08:42:34
392	87	42	1	2026-08-30 08:42:34
393	87	22	1	2026-08-30 08:42:34
394	87	41	1	2026-08-30 08:44:27
395	87	21	1	2026-08-30 08:44:27
396	87	42	1	2026-08-30 08:44:27
397	87	22	1	2026-08-30 08:44:27
398	87	41	1	2026-08-30 08:46:43
399	87	21	1	2026-08-30 08:46:43
400	87	42	1	2026-08-30 08:46:43
401	87	22	1	2026-08-30 08:46:43
402	88	41	1	2026-08-30 14:41:20
403	88	21	1	2026-08-30 14:41:20
404	88	42	1	2026-08-30 14:41:20
405	88	22	1	2026-08-30 14:41:20
406	88	41	1	2026-08-30 14:42:26
407	88	21	1	2026-08-30 14:42:26
408	88	42	1	2026-08-30 14:42:27
409	88	22	1	2026-08-30 14:42:27
410	89	41	1	2026-08-30 14:53:33
411	89	21	1	2026-08-30 14:53:33
412	89	42	1	2026-08-30 14:53:33
413	89	22	1	2026-08-30 14:53:33
414	90	41	1	2026-09-03 08:06:37
415	90	21	1	2026-09-03 08:06:37
416	90	42	1	2026-09-03 08:06:38
417	90	22	1	2026-09-03 08:06:38
418	91	41	1	2026-09-03 08:08:03
419	91	21	1	2026-09-03 08:08:03
420	91	42	1	2026-09-03 08:08:03
421	91	22	1	2026-09-03 08:08:03
422	92	41	1	2026-09-03 08:10:23
423	92	21	1	2026-09-03 08:10:23
424	92	42	1	2026-09-03 08:10:23
425	92	22	1	2026-09-03 08:10:23
426	93	41	1	2026-09-03 08:20:31
427	93	21	1	2026-09-03 08:20:31
428	93	42	1	2026-09-03 08:20:31
429	93	22	1	2026-09-03 08:20:31
430	94	41	1	2026-09-03 08:22:06
431	94	21	1	2026-09-03 08:22:06
432	94	42	1	2026-09-03 08:22:06
433	94	22	1	2026-09-03 08:22:06
434	95	41	1	2026-09-03 08:27:22
435	95	21	1	2026-09-03 08:27:22
436	95	42	1	2026-09-03 08:27:22
437	95	22	1	2026-09-03 08:27:22
438	96	41	1	2026-09-03 08:29:50
439	96	21	1	2026-09-03 08:29:50
440	96	42	1	2026-09-03 08:29:50
441	96	22	1	2026-09-03 08:29:50
442	97	41	1	2026-09-03 08:36:44
443	97	21	1	2026-09-03 08:36:44
444	97	42	1	2026-09-03 08:36:44
445	97	22	1	2026-09-03 08:36:44
446	95	41	1	2026-09-03 08:52:01
447	95	21	1	2026-09-03 08:52:01
448	95	42	1	2026-09-03 08:52:01
449	95	22	1	2026-09-03 08:52:01
450	98	41	1	2026-09-03 09:01:34
451	98	21	1	2026-09-03 09:01:34
452	98	42	1	2026-09-03 09:01:34
453	98	22	1	2026-09-03 09:01:34
454	99	41	1	2026-09-03 09:04:24
455	99	21	1	2026-09-03 09:04:24
456	99	42	1	2026-09-03 09:04:25
457	99	22	1	2026-09-03 09:04:25
458	100	41	1	2026-09-03 09:07:25
459	100	21	1	2026-09-03 09:07:25
460	100	42	1	2026-09-03 09:07:25
461	100	22	1	2026-09-03 09:07:25
462	101	41	1	2026-09-03 09:08:31
463	101	21	1	2026-09-03 09:08:31
464	101	42	1	2026-09-03 09:08:31
465	101	22	1	2026-09-03 09:08:31
466	103	140	1	2026-09-03 09:56:07
467	103	29	2	2026-09-03 10:00:20
468	103	27	3	2026-09-03 10:03:26
469	103	41	4	2026-09-03 10:05:48
470	103	21	4	2026-09-03 10:05:48
471	103	42	4	2026-09-03 10:05:48
472	103	22	4	2026-09-03 10:05:48
473	103	23	5	2026-09-03 10:09:34
474	102	140	1	2026-09-05 11:01:46
475	102	29	2	2026-09-05 11:03:35
476	102	27	3	2026-09-05 11:06:17
477	102	41	4	2026-09-05 11:06:41
478	102	21	4	2026-09-05 11:06:41
479	102	42	4	2026-09-05 11:06:41
480	102	22	4	2026-09-05 11:06:41
481	102	23	5	2026-09-05 11:07:43
482	102	24	6	2026-09-05 13:20:46
483	102	25	7	2026-09-05 13:21:15
484	104	22	1	2026-09-05 13:39:55
485	104	42	1	2026-09-05 13:39:55
486	104	41	1	2026-09-05 13:39:55
487	104	21	1	2026-09-05 13:39:56
\.


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cache (key, value, expiration) FROM stdin;
laravel-cache-7f3eaafa7c7e741c059cc24bcf23f343:timer	i:1783941091;	1783941092
laravel-cache-7f3eaafa7c7e741c059cc24bcf23f343	i:1;	1783941093
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: calendar_activities; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.calendar_activities (id, activity_calendar_id, name, description, venue, activity_date, start_time, end_time, created_at, updated_at, sdg, participant_program_assigned, budget) FROM stdin;
21	12	CS Foundations Orientation	\N	Room 201	2026-07-18	09:00:00	11:00:00	2026-08-21 05:51:56	2026-08-21 05:51:56	\N	\N	\N
22	12	Hackathon Kickoff	\N	Function Hall	2026-09-05	10:00:00	16:00:00	2026-08-21 05:51:57	2026-08-21 05:51:57	\N	\N	\N
23	12	Code Review Bootcamp	\N	AVR 1	2026-09-26	13:00:00	16:00:00	2026-08-21 05:51:58	2026-08-21 05:51:58	\N	\N	\N
24	13	Design Critique Night	\N	Room 201	2026-06-20	14:00:00	17:00:00	2026-08-21 05:53:06	2026-08-21 05:53:06	\N	\N	\N
25	13	Architecture Expo	\N	Gymnasium	2026-10-02	09:00:00	17:00:00	2026-08-21 05:53:07	2026-08-21 05:53:07	\N	\N	\N
26	14	Structures Symposium	\N	AVR 1	2026-07-05	13:00:00	16:00:00	2026-08-21 05:54:10	2026-08-21 05:54:10	\N	\N	\N
27	14	Bridge Design Challenge	\N	Function Hall	2026-10-15	08:00:00	17:00:00	2026-08-21 05:54:11	2026-08-21 05:54:11	\N	\N	\N
28	15	Mental Health Awareness Week	\N	AVR 1	2026-09-12	09:00:00	17:00:00	2026-08-21 05:55:18	2026-08-21 05:55:18	\N	\N	\N
29	15	Peer Counseling Training	\N	Room 201	2026-10-08	13:00:00	16:00:00	2026-08-21 05:55:19	2026-08-21 05:55:19	\N	\N	\N
30	16	MedTech Lab Safety Seminar	\N	Room 201	2026-07-25	09:00:00	12:00:00	2026-08-21 05:56:01	2026-08-21 05:56:01	\N	\N	\N
31	16	Blood Donation Drive	\N	Gymnasium	2026-09-18	08:00:00	15:00:00	2026-08-21 05:56:02	2026-08-21 05:56:02	\N	\N	\N
32	17	Accountancy Career Talk	\N	AVR 1	2026-06-28	13:00:00	15:00:00	2026-08-21 05:57:05	2026-08-21 05:57:05	\N	\N	\N
33	17	Mock CPA Board Exam	\N	Function Hall	2026-10-24	08:00:00	17:00:00	2026-08-21 05:57:06	2026-08-21 05:57:06	\N	\N	\N
34	18	First Aid Training	\N	Room 201	2026-07-11	09:00:00	16:00:00	2026-08-21 05:58:13	2026-08-21 05:58:13	\N	\N	\N
35	18	Community Blood Drive	\N	Gymnasium	2026-11-05	08:00:00	15:00:00	2026-08-21 05:58:14	2026-08-21 05:58:14	\N	\N	\N
36	19	Intramurals Gaming Night	\N	SHS Gymnasium	2026-07-30	17:00:00	21:00:00	2026-08-21 05:59:17	2026-08-21 05:59:17	\N	\N	\N
37	19	Valorant Campus Cup	\N	SHS Gymnasium	2026-09-19	13:00:00	18:00:00	2026-08-21 05:59:18	2026-08-21 05:59:18	\N	\N	\N
38	20	Civil Engineering Site Visit	\N	Site Visit - Batangas	2026-10-05	08:00:00	17:00:00	2026-08-21 06:00:24	2026-08-21 06:00:24	\N	\N	\N
39	21	SHS Talent Night	\N	SHS Function Room	2026-10-30	13:00:00	17:00:00	2026-08-21 06:05:26	2026-08-21 06:05:26	\N	\N	\N
40	22	Infrastructure Career Fair	\N	Function Hall Annex	2026-11-02	09:00:00	16:00:00	2026-08-21 06:09:56	2026-08-21 06:09:56	\N	\N	\N
41	23	Roblox Tournament	\N	Lab 635	2026-09-20	10:00:00	15:00:00	2026-08-23 12:53:41	2026-08-23 12:53:41	good_health_and_well_being	All Programs - All Year Levels	3000.00
42	24	Pakain Program	\N	AVR	2026-07-30	09:00:00	11:00:00	2026-08-24 12:25:52	2026-08-24 12:25:52	zero_hunger	ALL LEVELS	7000.00
44	25	PALARO	\N	Lab 634	2026-07-30	09:00:00	11:00:00	2026-08-24 12:50:32	2026-08-24 12:50:32	good_health_and_well_being	ALL LEVELS	3000.00
45	26	Student Leadership Workshop	\N	AVR	2026-08-25	08:30:00	12:00:00	2026-08-24 13:52:34	2026-08-24 13:52:34	\N	\N	\N
46	27	ACt 1	asdasdasd	gym	2026-09-10	13:00:00	16:00:00	2026-09-03 08:20:31	2026-09-03 08:20:31	zero_hunger	BSIT	2000.00
47	28	act 1	ahahaha	gym	2026-09-10	16:21:00	18:21:00	2026-09-03 08:22:06	2026-09-03 08:22:06	zero_hunger	bsit	10000.00
48	28	act2	aaaahahaha	gtym	2026-09-15	18:21:00	20:21:00	2026-09-03 08:22:06	2026-09-03 08:22:06	good_health_and_well_being	bscs	9000.00
52	30	gen 1	ahaha	gym	2026-09-10	18:23:00	20:23:00	2026-09-03 08:29:50	2026-09-03 08:29:50	zero_hunger	bste	2000.00
53	31	karltzy ganme	ahaha	AVR	2026-09-10	18:23:00	20:23:00	2026-09-03 08:36:44	2026-09-03 08:36:44	good_health_and_well_being	bsct	2000.00
54	29	act 1 dayet revised	haha revised	gytm revised	2026-09-10	18:23:00	20:23:00	2026-09-03 08:52:01	2026-09-03 08:52:01	zero_hunger	bscs revised	1000.00
55	29	act 2 dayet	i;jihjij	gymn	2026-09-17	14:26:00	17:26:00	2026-09-03 08:52:01	2026-09-03 08:52:01	affordable_and_clean_energy	bsn	7090.00
56	29	act 2 dayet revised	hjhj revised	gymn revised	2026-09-17	18:26:00	20:26:00	2026-09-03 08:52:01	2026-09-03 08:52:01	zero_hunger	bsb revised	9090.00
57	32	Sept 9 Act	no poverty	Gym	2026-09-08	17:01:00	19:01:00	2026-09-03 09:01:33	2026-09-03 09:01:33	no_poverty	BSIT	2000.00
58	33	Sept 9	asdasdasd	Gym	2026-09-08	17:06:00	19:02:00	2026-09-03 09:04:24	2026-09-03 09:04:24	good_health_and_well_being	asdasd	2000.00
59	34	approved gen	asdasdasd	603	2026-09-16	11:06:00	14:06:00	2026-09-03 09:07:25	2026-09-03 09:07:25	clean_water_and_sanitation	bsit	2000.00
60	35	check dayet	aksldjjkasld	604	2026-09-16	11:07:00	14:07:00	2026-09-03 09:08:31	2026-09-03 09:08:31	good_health_and_well_being	bsit	91283.00
\.


--
-- Data for Name: document_attachments; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.document_attachments (id, document_id, slot_key, original_filename, path, disk, mime_type, size, uploaded_by, created_at, updated_at) FROM stdin;
112	35	application_form	application_form.pdf	attachments/organization_registration/35/TqV8CTzz3mJc15UJuqLXfOzYQxviVFvI6clcpfSt.pdf	supabase	application/pdf	204800	91	2026-08-21 05:32:39	2026-08-21 05:32:39
113	35	by_laws	by_laws.pdf	attachments/organization_registration/35/z907YUpSu70EQjDuYvw0yV7AXGGz8u4yojv3c3g0.pdf	supabase	application/pdf	204800	91	2026-08-21 05:32:41	2026-08-21 05:32:41
115	35	dean_endorsement_letter	dean_endorsement_letter.pdf	attachments/organization_registration/35/uUS9IxiUHhn4TiJFZNr2oAeigOKqCPGYEhu7YNal.pdf	supabase	application/pdf	204800	91	2026-08-21 05:32:45	2026-08-21 05:32:45
116	35	proposed_projects_budget	proposed_projects_budget.pdf	attachments/organization_registration/35/ambEKuI9N7vSaOrS1QsxOBp3RWjgwozi3AGMlW6L.pdf	supabase	application/pdf	204800	91	2026-08-21 05:32:46	2026-08-21 05:32:46
117	36	letter_of_intent	letter_of_intent.pdf	attachments/organization_registration/36/bzovJGzOv0wSRLqMXSppn7uuVQ8L9zC7dLp5LUCZ.pdf	supabase	application/pdf	204800	92	2026-08-21 05:34:03	2026-08-21 05:34:03
118	36	application_form	application_form.pdf	attachments/organization_registration/36/Ap7ZVPfNkmVOEG3W2cYBaUHVQkwcW6ZgUXryCeU6.pdf	supabase	application/pdf	204800	92	2026-08-21 05:34:05	2026-08-21 05:34:05
119	36	by_laws	by_laws.pdf	attachments/organization_registration/36/IJc66MUIIP49tocZZa9qlxBqZgE6LVRd80fFt5Il.pdf	supabase	application/pdf	204800	92	2026-08-21 05:34:07	2026-08-21 05:34:07
120	36	officers_list	officers_list.pdf	attachments/organization_registration/36/DURVIodenFgoeQfM43W7pMVdARU7u1F1A3zwX85r.pdf	supabase	application/pdf	204800	92	2026-08-21 05:34:09	2026-08-21 05:34:09
121	36	dean_endorsement_letter	dean_endorsement_letter.pdf	attachments/organization_registration/36/h3tNiQvE0GJuVkx8hoeejk3mfr5C5zUglTs5Emlq.pdf	supabase	application/pdf	204800	92	2026-08-21 05:34:10	2026-08-21 05:34:10
122	36	proposed_projects_budget	proposed_projects_budget.pdf	attachments/organization_registration/36/JFgz3OfaWgahNoVql9P2VpDWYhyEOQeWbeivlEG8.pdf	supabase	application/pdf	204800	92	2026-08-21 05:34:12	2026-08-21 05:34:12
123	37	letter_of_intent	letter_of_intent.pdf	attachments/organization_registration/37/Iov2s35LzV21q2BgUAGEpxqChNoQlR8Qc1PKmExL.pdf	supabase	application/pdf	204800	93	2026-08-21 05:35:29	2026-08-21 05:35:29
124	37	application_form	application_form.pdf	attachments/organization_registration/37/SMNR4Z3B8DxkY7nnQPnmYjwzxletLljdFYEJs7zR.pdf	supabase	application/pdf	204800	93	2026-08-21 05:35:31	2026-08-21 05:35:31
125	37	by_laws	by_laws.pdf	attachments/organization_registration/37/w84qboGH2iKLadW32T0lvc9uiZJZFDybTqAIlA6L.pdf	supabase	application/pdf	204800	93	2026-08-21 05:35:33	2026-08-21 05:35:33
126	37	officers_list	officers_list.pdf	attachments/organization_registration/37/zJagEgzhgApz6qA2aZe9Kq3lGaZeNeRblopzTJUL.pdf	supabase	application/pdf	204800	93	2026-08-21 05:35:35	2026-08-21 05:35:35
127	37	dean_endorsement_letter	dean_endorsement_letter.pdf	attachments/organization_registration/37/XwvFcdHwOnDHZiXGQgUlwRnfIplmozT2oLF2TpX9.pdf	supabase	application/pdf	204800	93	2026-08-21 05:35:36	2026-08-21 05:35:36
128	37	proposed_projects_budget	proposed_projects_budget.pdf	attachments/organization_registration/37/YnGLZthomq7cjBgIEBi2fm9GsG1OFZxrZOQMLts1.pdf	supabase	application/pdf	204800	93	2026-08-21 05:35:38	2026-08-21 05:35:38
129	38	letter_of_intent	letter_of_intent.pdf	attachments/organization_registration/38/aAnn3ko6UlIe5roJGyNDWO8KepcKLZk4F6jUWM2q.pdf	supabase	application/pdf	204800	94	2026-08-21 05:36:55	2026-08-21 05:36:55
130	38	application_form	application_form.pdf	attachments/organization_registration/38/7A6H4Nmtv05WDFq5XXHa9qesJXPCN0STkWbsLlWy.pdf	supabase	application/pdf	204800	94	2026-08-21 05:36:57	2026-08-21 05:36:57
131	38	by_laws	by_laws.pdf	attachments/organization_registration/38/DddGRCYY0ayYFDuxIIFdWJWxapcP72AN4J6beIHj.pdf	supabase	application/pdf	204800	94	2026-08-21 05:36:59	2026-08-21 05:36:59
132	38	officers_list	officers_list.pdf	attachments/organization_registration/38/WjYx4mqVSjv14GV9hMTJJcqzejkvt844tL6fvys0.pdf	supabase	application/pdf	204800	94	2026-08-21 05:37:00	2026-08-21 05:37:00
133	38	dean_endorsement_letter	dean_endorsement_letter.pdf	attachments/organization_registration/38/L7oMDxxKCyW6S7s04vsxrMMBC3QSYLnu6Pl3ItIy.pdf	supabase	application/pdf	204800	94	2026-08-21 05:37:02	2026-08-21 05:37:02
134	38	proposed_projects_budget	proposed_projects_budget.pdf	attachments/organization_registration/38/vVZCSFVLm30xh1ImxP8FnA086wiki0eDdK89xbok.pdf	supabase	application/pdf	204800	94	2026-08-21 05:37:04	2026-08-21 05:37:04
135	39	letter_of_intent	letter_of_intent.pdf	attachments/organization_registration/39/ltgplTW90yKPNpccEac5PxzG5on00qahw25sKy34.pdf	supabase	application/pdf	204800	95	2026-08-21 05:38:22	2026-08-21 05:38:22
136	39	application_form	application_form.pdf	attachments/organization_registration/39/tmd9BBZCcuJh9j77gwqaeMSvkb91gnu6WirBZoL7.pdf	supabase	application/pdf	204800	95	2026-08-21 05:38:24	2026-08-21 05:38:24
137	39	by_laws	by_laws.pdf	attachments/organization_registration/39/ZFVUdy7giNqt9Iefv889JoZ3BQZIZHFzs1FdTtvQ.pdf	supabase	application/pdf	204800	95	2026-08-21 05:38:25	2026-08-21 05:38:25
138	39	officers_list	officers_list.pdf	attachments/organization_registration/39/CueoK2qPrOi3HsXtjlpBMMzPPwUH5TizILjti1Za.pdf	supabase	application/pdf	204800	95	2026-08-21 05:38:27	2026-08-21 05:38:27
139	39	dean_endorsement_letter	dean_endorsement_letter.pdf	attachments/organization_registration/39/c3BmAhAqVFoLILwUkrV7GhfrtUzo0MCWtJKnCukE.pdf	supabase	application/pdf	204800	95	2026-08-21 05:38:29	2026-08-21 05:38:29
140	39	proposed_projects_budget	proposed_projects_budget.pdf	attachments/organization_registration/39/IHKlqBRcqNkNlD22INkSQldSjsF7FHaeKlm3KSR1.pdf	supabase	application/pdf	204800	95	2026-08-21 05:38:30	2026-08-21 05:38:30
141	40	letter_of_intent	letter_of_intent.pdf	attachments/organization_registration/40/IEjkZ1IkElgfWJPutNbG3Ll1Bh7P4gXHttNaabzt.pdf	supabase	application/pdf	204800	96	2026-08-21 05:39:48	2026-08-21 05:39:48
142	40	application_form	application_form.pdf	attachments/organization_registration/40/PnosPmQ5Yan5wPuh82VgQ1ixsMUeJ72IfpiMaWCk.pdf	supabase	application/pdf	204800	96	2026-08-21 05:39:50	2026-08-21 05:39:50
143	40	by_laws	by_laws.pdf	attachments/organization_registration/40/5Gf54raX250twp0w1yBRvNchFNLMzb7IMPxYaAQx.pdf	supabase	application/pdf	204800	96	2026-08-21 05:39:52	2026-08-21 05:39:52
144	40	officers_list	officers_list.pdf	attachments/organization_registration/40/DfPiqPkOwQtRzOjz4xqoezCowePv9vDsF1yHIpOu.pdf	supabase	application/pdf	204800	96	2026-08-21 05:39:54	2026-08-21 05:39:54
146	40	proposed_projects_budget	proposed_projects_budget.pdf	attachments/organization_registration/40/sDTDna2bGbtyvVTYGhctRwJbeJt7HT6INofejIa8.pdf	supabase	application/pdf	204800	96	2026-08-21 05:39:57	2026-08-21 05:39:57
148	41	application_form	application_form.pdf	attachments/organization_registration/41/QH6302pwahIuot9fpV0wIDwd2li5b3XRhoUXjUxm.pdf	supabase	application/pdf	204800	97	2026-08-21 05:41:17	2026-08-21 05:41:17
149	41	by_laws	by_laws.pdf	attachments/organization_registration/41/UHpRqxy38wnmUWXchlcIWTuSVP3gktBaUPo3xU59.pdf	supabase	application/pdf	204800	97	2026-08-21 05:41:18	2026-08-21 05:41:18
150	41	officers_list	officers_list.pdf	attachments/organization_registration/41/oPvKJDOvWOTGaoKya1ZJKWjny4FttvzHGRwxSOFa.pdf	supabase	application/pdf	204800	97	2026-08-21 05:41:20	2026-08-21 05:41:20
151	41	dean_endorsement_letter	dean_endorsement_letter.pdf	attachments/organization_registration/41/gnhpfeZmW9dIpCbKGIEyCuYtIkAKyrNUfKbZsvmm.pdf	supabase	application/pdf	204800	97	2026-08-21 05:41:22	2026-08-21 05:41:22
153	42	letter_of_intent	letter_of_intent.pdf	attachments/organization_registration/42/ej4AdTmHquDPpAvtZcRBnICbs2nRcxZgfnnGQhmd.pdf	supabase	application/pdf	204800	98	2026-08-21 05:42:41	2026-08-21 05:42:41
154	42	application_form	application_form.pdf	attachments/organization_registration/42/5kfImGN5Pp2bYRdk4siEOduPJTM7F7ubCsKBytSR.pdf	supabase	application/pdf	204800	98	2026-08-21 05:42:43	2026-08-21 05:42:43
155	42	by_laws	by_laws.pdf	attachments/organization_registration/42/bOIxI5XTzdG5NI1uHXMgBQvndZD6MdRuPQAsXpEx.pdf	supabase	application/pdf	204800	98	2026-08-21 05:42:45	2026-08-21 05:42:45
156	42	officers_list	officers_list.pdf	attachments/organization_registration/42/gZnGQ9qnCAblVLBFXH6OEegYD8zTKVktu5bKnQ7c.pdf	supabase	application/pdf	204800	98	2026-08-21 05:42:47	2026-08-21 05:42:47
157	42	dean_endorsement_letter	dean_endorsement_letter.pdf	attachments/organization_registration/42/YAUD0JCpemtV0qkmVgvpESZw5LzT70wiyW6Ja7Jw.pdf	supabase	application/pdf	204800	98	2026-08-21 05:42:48	2026-08-21 05:42:48
158	42	proposed_projects_budget	proposed_projects_budget.pdf	attachments/organization_registration/42/5okyEoZi5VOroo5FLlw6lNh52YtXUcAbWfnVOg8U.pdf	supabase	application/pdf	204800	98	2026-08-21 05:42:50	2026-08-21 05:42:50
159	44	letter_of_intent	letter_of_intent.pdf	attachments/organization_registration/44/5oqRIJXMXhYs3kY1hXfYOSFNKKUXk6ZCQowwfeWH.pdf	supabase	application/pdf	204800	103	2026-08-21 05:44:29	2026-08-21 05:44:29
160	44	application_form	application_form.pdf	attachments/organization_registration/44/Fg85fN6pquyHXjBNo5PmzM68AJ3zAYUtxOywJhIO.pdf	supabase	application/pdf	204800	103	2026-08-21 05:44:31	2026-08-21 05:44:31
161	44	by_laws	by_laws.pdf	attachments/organization_registration/44/amTtCqiP8W75uKjQlGWbswLw2CmRNsbzniif6EK9.pdf	supabase	application/pdf	204800	103	2026-08-21 05:44:32	2026-08-21 05:44:32
162	44	officers_list	officers_list.pdf	attachments/organization_registration/44/DCoshgngMccy5RrY0yhNYiYDP5yTaI2JA6yPWtBx.pdf	supabase	application/pdf	204800	103	2026-08-21 05:44:34	2026-08-21 05:44:34
163	44	dean_endorsement_letter	dean_endorsement_letter.pdf	attachments/organization_registration/44/p5sYP5uhe0Cz8e64CbIS8bbbdpT2XpP58fRKjFbS.pdf	supabase	application/pdf	204800	103	2026-08-21 05:44:36	2026-08-21 05:44:36
164	44	proposed_projects_budget	proposed_projects_budget.pdf	attachments/organization_registration/44/zAltZjD4iBr4fTouoVjGkEQWeG7hxUNtcPEHC59Y.pdf	supabase	application/pdf	204800	103	2026-08-21 05:44:38	2026-08-21 05:44:38
165	45	letter_of_intent	letter_of_intent.pdf	attachments/organization_registration/45/E1i48Fl928cRLsTmOgXZ0S2BiknhWosSiKBNb3SB.pdf	supabase	application/pdf	204800	104	2026-08-21 05:45:25	2026-08-21 05:45:25
166	45	application_form	application_form.pdf	attachments/organization_registration/45/3hjNRVxewo11WbbJLVYVzyrZSxzQ5fEAgpZC960Z.pdf	supabase	application/pdf	204800	104	2026-08-21 05:45:26	2026-08-21 05:45:26
167	45	by_laws	by_laws.pdf	attachments/organization_registration/45/FhsVfpKw5QIePCGLXSnLPOvLYACuyQ4e8PGAOgFX.pdf	supabase	application/pdf	204800	104	2026-08-21 05:45:28	2026-08-21 05:45:28
168	45	officers_list	officers_list.pdf	attachments/organization_registration/45/g2RdhgO2gW8ChuIXDymUNgEIDOVqwMU7PY8bfO2H.pdf	supabase	application/pdf	204800	104	2026-08-21 05:45:30	2026-08-21 05:45:30
169	45	dean_endorsement_letter	dean_endorsement_letter.pdf	attachments/organization_registration/45/vFRpq3cxWjEsLdufUcDC4Ydb6piTtANa0vjFMehr.pdf	supabase	application/pdf	204800	104	2026-08-21 05:45:32	2026-08-21 05:45:32
170	45	proposed_projects_budget	proposed_projects_budget.pdf	attachments/organization_registration/45/9y5vzc9ZL7lQHazkcQwXIEPJDLpJ5c9hREtoS7vQ.pdf	supabase	application/pdf	204800	104	2026-08-21 05:45:33	2026-08-21 05:45:33
171	46	letter_of_intent	letter_of_intent.pdf	attachments/organization_registration/46/LNkMubvkaqoF814vuh3rSslcVfjDyea47EF2EggU.pdf	supabase	application/pdf	204800	105	2026-08-21 05:46:22	2026-08-21 05:46:22
172	46	application_form	application_form.pdf	attachments/organization_registration/46/UwqTsSNQe0kyzjFMsgVvAhXJ78AIpQFvBdaDpVD0.pdf	supabase	application/pdf	204800	105	2026-08-21 05:46:24	2026-08-21 05:46:24
173	46	by_laws	by_laws.pdf	attachments/organization_registration/46/ZUAns9hqwQb49XlGxj9scFVADscsIqeeig5Xe4c9.pdf	supabase	application/pdf	204800	105	2026-08-21 05:46:26	2026-08-21 05:46:26
174	46	officers_list	officers_list.pdf	attachments/organization_registration/46/xf6Kp7MjaqvWCGGyOkUQTDEmhPqKV4nXLuQodB1z.pdf	supabase	application/pdf	204800	105	2026-08-21 05:46:27	2026-08-21 05:46:27
175	46	dean_endorsement_letter	dean_endorsement_letter.pdf	attachments/organization_registration/46/JwvdqnCyav05ezJ9a7CVVIziixI3i5PR6BOQz71j.pdf	supabase	application/pdf	204800	105	2026-08-21 05:46:29	2026-08-21 05:46:29
176	46	proposed_projects_budget	proposed_projects_budget.pdf	attachments/organization_registration/46/MkobjxBpOwrRSA7PY6szoKNrJuwg1UnBWm2B953g.pdf	supabase	application/pdf	204800	105	2026-08-21 05:46:31	2026-08-21 05:46:31
177	48	letter_of_intent	letter_of_intent.pdf	attachments/organization_renewal/48/J8c7RRTFCDfRQuQHpaQAE8Ll2ROVYSK7jaNACQLe.pdf	supabase	application/pdf	204800	92	2026-08-21 05:47:27	2026-08-21 05:47:27
178	48	application_form	application_form.pdf	attachments/organization_renewal/48/ZsKB4aBghAu9hTObZ7Tv5wsJkciCsvePPiLPdqzW.pdf	supabase	application/pdf	204800	92	2026-08-21 05:47:29	2026-08-21 05:47:29
179	48	by_laws	by_laws.pdf	attachments/organization_renewal/48/KARaswVEucRTJgezRAoXLJMVxU28EdKrUEKfAtpZ.pdf	supabase	application/pdf	204800	92	2026-08-21 05:47:31	2026-08-21 05:47:31
180	48	officers_list	officers_list.pdf	attachments/organization_renewal/48/YZB8323P6CfZs56G2jC4Lo5W9K2T1FEAM98gmMsp.pdf	supabase	application/pdf	204800	92	2026-08-21 05:47:32	2026-08-21 05:47:32
181	48	dean_endorsement_letter	dean_endorsement_letter.pdf	attachments/organization_renewal/48/8DI9iYT7d7bLv427tR9fnXXtUoqEWGZ8rhODS9lF.pdf	supabase	application/pdf	204800	92	2026-08-21 05:47:34	2026-08-21 05:47:34
182	48	proposed_projects_budget	proposed_projects_budget.pdf	attachments/organization_renewal/48/BH6nEWo0k5G1Ihq5NyUDhFFZIwJ9RYntgIf1qmew.pdf	supabase	application/pdf	204800	92	2026-08-21 05:47:36	2026-08-21 05:47:36
184	48	financial_statement	financial_statement.pdf	attachments/organization_renewal/48/2PHmemdkpOKID5jZDZkErEYgFj4Stu0N5K5bwpqV.pdf	supabase	application/pdf	204800	92	2026-08-21 05:47:39	2026-08-21 05:47:39
186	49	letter_of_intent	letter_of_intent.pdf	attachments/organization_renewal/49/Pg89me6VW0ctVhj1LGyjGQOoh24SUElalj9lg5dP.pdf	supabase	application/pdf	204800	93	2026-08-21 05:48:27	2026-08-21 05:48:27
187	49	application_form	application_form.pdf	attachments/organization_renewal/49/Uemykzfx2ReCEEVl8xS0iPkacbLOj1DR2hoCVdxD.pdf	supabase	application/pdf	204800	93	2026-08-21 05:48:29	2026-08-21 05:48:29
188	49	by_laws	by_laws.pdf	attachments/organization_renewal/49/sKVgTT07dqQYLPMDtoxi2VpL0RHSgld8f7RvEPx6.pdf	supabase	application/pdf	204800	93	2026-08-21 05:48:30	2026-08-21 05:48:30
189	49	officers_list	officers_list.pdf	attachments/organization_renewal/49/5bZs2iSMglhMtdUYIghWbEZUg0CarnTDLynVrkJb.pdf	supabase	application/pdf	204800	93	2026-08-21 05:48:32	2026-08-21 05:48:32
190	49	dean_endorsement_letter	dean_endorsement_letter.pdf	attachments/organization_renewal/49/MABe2GFboZu8rwlgBUENjWKqLqB9hBtjF4BJSB0Q.pdf	supabase	application/pdf	204800	93	2026-08-21 05:48:34	2026-08-21 05:48:34
191	49	proposed_projects_budget	proposed_projects_budget.pdf	attachments/organization_renewal/49/flKnibLKdXrFG4VvD5edizHvuBDJJ5HokwFYC50l.pdf	supabase	application/pdf	204800	93	2026-08-21 05:48:35	2026-08-21 05:48:35
192	49	past_projects_list	past_projects_list.pdf	attachments/organization_renewal/49/zVBFhSQuwj9UCQ2EmHXBvHXGdIzvmJcbUcSgsU2J.pdf	supabase	application/pdf	204800	93	2026-08-21 05:48:37	2026-08-21 05:48:37
193	49	financial_statement	financial_statement.pdf	attachments/organization_renewal/49/pZOpvjvZQ0rwq9Dt2j12BrT6cxqRPVsedzfjR9oU.pdf	supabase	application/pdf	204800	93	2026-08-21 05:48:39	2026-08-21 05:48:39
194	49	evaluation_summary	evaluation_summary.pdf	attachments/organization_renewal/49/cD2N9UOd2BYyQVGRcLrbm9831ttaeGoUcL8B5Pmt.pdf	supabase	application/pdf	204800	93	2026-08-21 05:48:41	2026-08-21 05:48:41
195	50	letter_of_intent	letter_of_intent.pdf	attachments/organization_renewal/50/3SrncXc54UqUX72usnunpWQtiuoZRvnzcBhEbQVL.pdf	supabase	application/pdf	204800	96	2026-08-21 05:49:35	2026-08-21 05:49:35
196	50	application_form	application_form.pdf	attachments/organization_renewal/50/c3dLPvcDszDNzu9d56LQxFN4nmztARF25An4VsJE.pdf	supabase	application/pdf	204800	96	2026-08-21 05:49:37	2026-08-21 05:49:37
197	50	by_laws	by_laws.pdf	attachments/organization_renewal/50/yOvicAy1HyNObo9MMc8hmk6rZF4Uwy8xV4MBxRvN.pdf	supabase	application/pdf	204800	96	2026-08-21 05:49:39	2026-08-21 05:49:39
198	50	officers_list	officers_list.pdf	attachments/organization_renewal/50/I1ul01RU6syXCmjhypwBgoM3EOhHb9AzLSForiLC.pdf	supabase	application/pdf	204800	96	2026-08-21 05:49:40	2026-08-21 05:49:40
199	50	dean_endorsement_letter	dean_endorsement_letter.pdf	attachments/organization_renewal/50/RRQu4TUfd9BzqmHtlFjkUcgWdyCFjhObAdmvEI5j.pdf	supabase	application/pdf	204800	96	2026-08-21 05:49:42	2026-08-21 05:49:42
200	50	proposed_projects_budget	proposed_projects_budget.pdf	attachments/organization_renewal/50/KINF6BvAIWeqqMYMNXfviRHwHQVLNhZU67cYUXGv.pdf	supabase	application/pdf	204800	96	2026-08-21 05:49:44	2026-08-21 05:49:44
201	50	past_projects_list	past_projects_list.pdf	attachments/organization_renewal/50/HjvTII6YjLi9aql8d2dZrJzSlYReiUIwE6oGgQNs.pdf	supabase	application/pdf	204800	96	2026-08-21 05:49:45	2026-08-21 05:49:45
202	50	financial_statement	financial_statement.pdf	attachments/organization_renewal/50/0WuWXTPnNoOftsGfQZYlG4TehYhCdN1pOQ9PLvVy.pdf	supabase	application/pdf	204800	96	2026-08-21 05:49:47	2026-08-21 05:49:47
203	50	evaluation_summary	evaluation_summary.pdf	attachments/organization_renewal/50/8aYHCgfSuowoJZzA2pDt0O8zVfEhBn4WdAmPBKmm.pdf	supabase	application/pdf	204800	96	2026-08-21 05:49:49	2026-08-21 05:49:49
204	51	letter_of_intent	letter_of_intent.pdf	attachments/organization_renewal/51/EfogMIpeVIEmi571vZ7zUaLQIZNn8gjjhJWTbcdR.pdf	supabase	application/pdf	204800	97	2026-08-21 05:50:55	2026-08-21 05:50:55
205	51	application_form	application_form.pdf	attachments/organization_renewal/51/mBOT4KwpCEExjmmPV1WyLIzNHiWdXR6puKOSMlXy.pdf	supabase	application/pdf	204800	97	2026-08-21 05:50:57	2026-08-21 05:50:57
206	51	by_laws	by_laws.pdf	attachments/organization_renewal/51/6eFACHPyoE5w1Hc1YjF2SsGggefJZ3Xd5AGBRdrs.pdf	supabase	application/pdf	204800	97	2026-08-21 05:50:58	2026-08-21 05:50:58
207	51	officers_list	officers_list.pdf	attachments/organization_renewal/51/oN91CixtSS2v3xSuxW7A3HIEeeIRDdKmoR7lHy2r.pdf	supabase	application/pdf	204800	97	2026-08-21 05:51:00	2026-08-21 05:51:00
208	51	dean_endorsement_letter	dean_endorsement_letter.pdf	attachments/organization_renewal/51/dCHOan3XEiyeZRTfWzrewjkYAK0rFQOb5KrIdqmn.pdf	supabase	application/pdf	204800	97	2026-08-21 05:51:02	2026-08-21 05:51:02
209	51	proposed_projects_budget	proposed_projects_budget.pdf	attachments/organization_renewal/51/gWuMCRPsy4R069olEhGhXEAoJYZOLBTAZuh3QicP.pdf	supabase	application/pdf	204800	97	2026-08-21 05:51:04	2026-08-21 05:51:04
210	51	past_projects_list	past_projects_list.pdf	attachments/organization_renewal/51/XiUERPniItaHFKhRGGCvGr9GcRoLhSnvzeUfduOf.pdf	supabase	application/pdf	204800	97	2026-08-21 05:51:05	2026-08-21 05:51:05
211	51	financial_statement	financial_statement.pdf	attachments/organization_renewal/51/iNzIHLQypkfPdm1zmkkFxHPa7v427DRoo1f09y4N.pdf	supabase	application/pdf	204800	97	2026-08-21 05:51:07	2026-08-21 05:51:07
212	51	evaluation_summary	evaluation_summary.pdf	attachments/organization_renewal/51/qlvT6GE39aIIehHHUzHrFF1fCaMs9MRQ9RrfX44R.pdf	supabase	application/pdf	204800	97	2026-08-21 05:51:09	2026-08-21 05:51:09
213	67	photos	photos-1.jpg	attachments/after_activity_report/67/aQZuKHlp9GxZRetahffXKKpBHb7LNPedShhlGfCt.jpg	supabase	image/jpeg	204800	98	2026-08-21 06:13:39	2026-08-21 06:13:39
214	67	photos	photos-2.jpg	attachments/after_activity_report/67/PN84MFcZKoq0v6OOGIhMBc292efqqooKVFZta300.jpg	supabase	image/jpeg	204800	98	2026-08-21 06:13:39	2026-08-21 06:13:39
215	67	evaluation_form	evaluation_form.pdf	attachments/after_activity_report/67/okJrsrRYoShMFmPUiRie2d1w3qLzFSdQLMr0TWme.pdf	supabase	application/pdf	204800	98	2026-08-21 06:13:41	2026-08-21 06:13:41
216	67	attendance_sheet	attendance_sheet.pdf	attachments/after_activity_report/67/oGbnchh6uEo6gBzF2mDXMamTSJ6M2YJ6p9lRJRPc.pdf	supabase	application/pdf	204800	98	2026-08-21 06:13:43	2026-08-21 06:13:43
217	68	photos	photos-1.jpg	attachments/after_activity_report/68/NebcpqTki6mWy6QSp7VimYQIgRqk67GJRQWb4LO0.jpg	supabase	image/jpeg	204800	91	2026-08-21 06:14:47	2026-08-21 06:14:47
218	68	photos	photos-2.jpg	attachments/after_activity_report/68/gkNTwBWwETcOfTq33eyi4Afxt1ueKKpOjm0M4gys.jpg	supabase	image/jpeg	204800	91	2026-08-21 06:14:48	2026-08-21 06:14:48
219	68	evaluation_form	evaluation_form.pdf	attachments/after_activity_report/68/FUgYIAlE0Vw6UQy8Vrqp6pO0uDwd2mLMKCwgsMff.pdf	supabase	application/pdf	204800	91	2026-08-21 06:14:49	2026-08-21 06:14:49
111	35	letter_of_intent	letter_of_intent.pdf	attachments/organization_registration/35/FVsK8l0jbJy8u2FPlzx0c9WpoiPZnOf8i0keiSeO.pdf	supabase	application/pdf	204800	91	2026-08-21 05:32:38	2026-08-21 05:32:38
114	35	officers_list	officers_list.pdf	attachments/organization_registration/35/GxzsuiA0Po9PbA9X9QxsJDZjGRJO53FeHY150Xlq.pdf	supabase	application/pdf	204800	91	2026-08-21 05:32:43	2026-08-21 05:32:43
145	40	dean_endorsement_letter	dean_endorsement_letter.pdf	attachments/organization_registration/40/K8TVIrpAajUvnnESF8nNlBaXyl8hFkZXkAHt3Lf3.pdf	supabase	application/pdf	204800	96	2026-08-21 05:39:55	2026-08-21 05:39:55
147	41	letter_of_intent	letter_of_intent.pdf	attachments/organization_registration/41/jB1241m1OiwRMuDkilGmCVB96rdL9YFSCPTmDkj2.pdf	supabase	application/pdf	204800	97	2026-08-21 05:41:15	2026-08-21 05:41:15
152	41	proposed_projects_budget	proposed_projects_budget.pdf	attachments/organization_registration/41/v9Q9dOABSJPjd0qTM9PP2u9SkW8e2wugGP1ubUK7.pdf	supabase	application/pdf	204800	97	2026-08-21 05:41:24	2026-08-21 05:41:24
183	48	past_projects_list	past_projects_list.pdf	attachments/organization_renewal/48/gRrmeJ0Ysn43XSAOrdpP2wvCQP4hMZIbS7gB1Zqu.pdf	supabase	application/pdf	204800	92	2026-08-21 05:47:38	2026-08-21 05:47:38
185	48	evaluation_summary	evaluation_summary.pdf	attachments/organization_renewal/48/W2C43RwCCmxiBayf17yGoapcDlPbrVCD5YlU6frQ.pdf	supabase	application/pdf	204800	92	2026-08-21 05:47:41	2026-08-21 05:47:41
220	68	attendance_sheet	attendance_sheet.pdf	attachments/after_activity_report/68/a6K3nDs3QalSzfXNkwPyttyR4FL5wMsUe0nvdXMl.pdf	supabase	application/pdf	204800	91	2026-08-21 06:14:51	2026-08-21 06:14:51
221	69	photos	photos-1.jpg	attachments/after_activity_report/69/Z4p2SJh4Ub228KUI4HCmxwHHJOgmEAqTYeU4nskn.jpg	supabase	image/jpeg	204800	93	2026-08-21 06:15:35	2026-08-21 06:15:35
222	69	photos	photos-2.jpg	attachments/after_activity_report/69/nh7QUM795sbhjwcdgHRJKDAfbz7Yfw4Qtk0CC4l6.jpg	supabase	image/jpeg	204800	93	2026-08-21 06:15:36	2026-08-21 06:15:36
223	69	evaluation_form	evaluation_form.pdf	attachments/after_activity_report/69/urWPNkTODQoYIcqfmZGpHxaeqd7QQVwIgXahB1bm.pdf	supabase	application/pdf	204800	93	2026-08-21 06:15:37	2026-08-21 06:15:37
224	69	attendance_sheet	attendance_sheet.pdf	attachments/after_activity_report/69/GRPC8TiRXrH23xeJp0GFddiRh43ocx1i6GOXUACw.pdf	supabase	application/pdf	204800	93	2026-08-21 06:15:39	2026-08-21 06:15:39
225	71	letter_of_intent	image_2026-08-24_135004434.png	attachments/organization_registration/71/UqcpuVXsQyPwb4AhNxLwHMfACKPzNjtyLIABRAKd.png	supabase	image/png	18719	121	2026-08-24 05:50:24	2026-08-24 05:50:24
226	71	application_form	image_2026-08-24_135006307.png	attachments/organization_registration/71/4Xcz6RzNA8J1baAkF71Ysk0JTJUrrXX0yCHL1v14.png	supabase	image/png	18719	121	2026-08-24 05:50:25	2026-08-24 05:50:25
228	71	officers_list	image_2026-08-24_135011250.png	attachments/organization_registration/71/jXMsOPc2Gtc18RH9K5vQADyuifY2zKl5No0JfwHk.png	supabase	image/png	18719	121	2026-08-24 05:50:26	2026-08-24 05:50:26
229	71	dean_endorsement_letter	image_2026-08-24_135012875.png	attachments/organization_registration/71/QrH516raQNwPOgsi33K7CeGcMRQ7qEl4KvYDUpAP.png	supabase	image/png	18719	121	2026-08-24 05:50:27	2026-08-24 05:50:27
230	71	proposed_projects_budget	image_2026-08-24_135015843.png	attachments/organization_registration/71/ojWXVLjburVysGbUCMtIEtoWDR0vDlYZt6uUBmZQ.png	supabase	image/png	18719	121	2026-08-24 05:50:28	2026-08-24 05:50:28
231	71	by_laws	image_2026-08-24_135008218.png	attachments/organization_registration/71/FrmVjSrdZX7UaioYOOhR1IlMlxPIhCgJpQKM7Tvm.png	supabase	image/png	18719	121	2026-08-24 06:16:17	2026-08-24 06:16:17
232	72	letter_of_intent	image_2026-08-24_135008218.png	attachments/organization_renewal/72/n2KVfiBZtfpr3mJkVmRinLeo63VQyxSGcXKFJixg.png	supabase	image/png	18719	97	2026-08-24 06:29:48	2026-08-24 06:29:48
233	72	application_form	image_2026-08-24_135008218.png	attachments/organization_renewal/72/JMIPIoHrID1s3bYj8tj7rnLylluT5N5UU0yMGd3o.png	supabase	image/png	18719	97	2026-08-24 06:29:50	2026-08-24 06:29:50
234	72	by_laws	image_2026-08-24_135008218.png	attachments/organization_renewal/72/ie5rNYkSLqjCSIzGRHfTA7ZZRszy1BxRrWzhSlW9.png	supabase	image/png	18719	97	2026-08-24 06:29:50	2026-08-24 06:29:50
235	72	officers_list	image_2026-08-24_135008218.png	attachments/organization_renewal/72/f8cIyXjlMnB6CBIWH6a1JOuIlV9yNsqBGJx3Hzzl.png	supabase	image/png	18719	97	2026-08-24 06:29:51	2026-08-24 06:29:51
236	72	dean_endorsement_letter	image_2026-08-24_135008218.png	attachments/organization_renewal/72/M2EtDweUjtoA2BKCKT5BnLkFwrrVtOZeqCA8gP6m.png	supabase	image/png	18719	97	2026-08-24 06:29:52	2026-08-24 06:29:52
237	72	proposed_projects_budget	image_2026-08-24_135008218.png	attachments/organization_renewal/72/UNsXDUqTd65RQ0mCP4e9ViHdjYpHjqhNb5HPL2jJ.png	supabase	image/png	18719	97	2026-08-24 06:29:53	2026-08-24 06:29:53
238	72	past_projects_list	image_2026-08-24_135008218.png	attachments/organization_renewal/72/iwfJ8MOSYNAkK882zHHms11PAy0AB4RIPkmCWVch.png	supabase	image/png	18719	97	2026-08-24 06:29:53	2026-08-24 06:29:53
239	72	financial_statement	image_2026-08-24_135008218.png	attachments/organization_renewal/72/uXDyzT6lhqGSZ9wyKxnUhYPPxyzKj1xJrcmTYy54.png	supabase	image/png	18719	97	2026-08-24 06:29:54	2026-08-24 06:29:54
240	72	evaluation_summary	image_2026-08-24_135008218.png	attachments/organization_renewal/72/aHeG6NoSMIjHPk2vQbIpLg8STuzWTGbpNXLQh6DE.png	supabase	image/png	18719	97	2026-08-24 06:29:55	2026-08-24 06:29:55
241	73	letter_of_intent	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/73/trBeYHN5DMoRmNVQHG3D91YdtOW3y82xhNebqE9Q.pdf	supabase	application/pdf	121241	\N	2026-08-24 08:48:58	2026-08-24 08:48:58
242	73	application_form	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/73/NL5WLDqw6NCB5m29k5vUrKWhXt9isVbaN1vyzbwb.pdf	supabase	application/pdf	121241	\N	2026-08-24 08:48:58	2026-08-24 08:48:58
243	73	by_laws	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/73/RHyBL8Mq7Ud7duCSdSPJNC3C6Kop6oIe8q1W4ZJd.pdf	supabase	application/pdf	121241	\N	2026-08-24 08:48:59	2026-08-24 08:48:59
244	73	officers_list	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/73/cEJFOoZN4ruDUm5n6JGg225Mi6efpUBah8YwoClz.pdf	supabase	application/pdf	121241	\N	2026-08-24 08:49:00	2026-08-24 08:49:00
245	73	dean_endorsement_letter	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/73/WSAkJT0hqAqR6J6UHVwGnkByy9fvMoMeWcdvEyAS.pdf	supabase	application/pdf	121241	\N	2026-08-24 08:49:01	2026-08-24 08:49:01
246	73	proposed_projects_budget	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/73/Bxtp0ltj1I7aUj7rMUaAxSajJt7cozrrIG3dJB56.pdf	supabase	application/pdf	121241	\N	2026-08-24 08:49:02	2026-08-24 08:49:02
254	78	resume_of_resource_person	Manalo, Marian Justine - Resume.pdf	attachments/activity_proposal/78/OUXsTeKYeVm0HRiNbPs6j0AMLoiKdpqsdKXHuER4.pdf	supabase	application/pdf	128552	121	2026-08-24 13:06:16	2026-08-24 13:06:16
255	79	resume_of_resource_person	Manalo, Marian Justine - Resume.pdf	attachments/activity_proposal/79/YHbrU5ujWbvbOjRKJNBhWX7QtlJuT4hUXeXxVdx9.pdf	supabase	application/pdf	128552	121	2026-08-24 13:54:51	2026-08-24 13:54:51
256	80	resume_of_resource_person	Manalo, Marian Justine - Resume (1).pdf	attachments/activity_proposal/80/ruREvk6lHMNOaIEmCDYt3koY7KV1tQ3pYyGNpR8N.pdf	supabase	application/pdf	128552	96	2026-08-24 16:22:38	2026-08-24 16:22:38
257	81	photos	it-specialist-networking.png	attachments/after_activity_report/81/9ZhZAqr7kpN6bSGitc5rwZPDyG2b8XljzegDLVLf.png	supabase	image/png	280036	96	2026-08-24 17:06:07	2026-08-24 17:06:07
258	81	evaluation_form	Manalo, Marian Justine - Resume (1).pdf	attachments/after_activity_report/81/28Wf141yzvQEeL1Dy3QPZAZyh6o0iHBrS8ckH8zf.pdf	supabase	application/pdf	128552	96	2026-08-24 17:06:08	2026-08-24 17:06:08
259	81	attendance_sheet	Manalo, Marian Justine - Resume (1).pdf	attachments/after_activity_report/81/rt1tr50WypFt1weZogYLvQ1qpRZh1CrvUloan5zQ.pdf	supabase	application/pdf	128552	96	2026-08-24 17:06:09	2026-08-24 17:06:09
247	74	letter_of_intent	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/74/QU2TUCM0Gh2CLVVKJFr3LiBJulmQaLUNqqLgOwTs.pdf	supabase	application/pdf	121241	\N	2026-08-24 09:12:06	2026-08-24 09:12:06
248	74	application_form	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/74/OrmQdAUVTW6YGmpjrFSN5gap1umfhiBRL6BwDOFs.pdf	supabase	application/pdf	121241	\N	2026-08-24 09:12:07	2026-08-24 09:12:07
249	74	by_laws	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/74/qKPUvfKmsRG5POwgvlJJbTq7mQ15rPVHmmfHD1d9.pdf	supabase	application/pdf	121241	\N	2026-08-24 09:12:08	2026-08-24 09:12:08
250	74	officers_list	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/74/PHUw33p2ocxXGRJdHekwvchJvYN7y2ZCssQ40VNL.pdf	supabase	application/pdf	121241	\N	2026-08-24 09:12:09	2026-08-24 09:12:09
251	74	dean_endorsement_letter	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/74/cr4oiQ7EFnBq1DAqxikP6TKm7Kw4KgWL8wfQ79Uc.pdf	supabase	application/pdf	121241	\N	2026-08-24 09:12:10	2026-08-24 09:12:10
253	74	proposed_projects_budget	Week 3 - Activity 2.1 (ITELEC5L) e-Commerce Infrastructure.pdf	attachments/organization_registration/74/vmwxdfJu2nb9cQhwGb9hkA4b1js3vdpf8Np7KRqO.pdf	supabase	application/pdf	175878	\N	2026-08-24 09:27:54	2026-08-24 09:27:54
266	83	letter_of_intent	KEYTAKEAWAYS.pdf	attachments/organization_registration/83/shyhKtJN0rkc9V4OVJMyqfWVsYiWzoOXvMhz9r7B.pdf	supabase	application/pdf	107792	131	2026-08-30 02:54:04	2026-08-30 02:54:04
267	83	application_form	KEYTAKEAWAYS.pdf	attachments/organization_registration/83/4krzmeEz255ugnBXBr0IB1snAaZafJhtG9cSNhRd.pdf	supabase	application/pdf	107792	131	2026-08-30 02:54:05	2026-08-30 02:54:05
268	83	by_laws	KEYTAKEAWAYS.pdf	attachments/organization_registration/83/fv7cefCoNOUdJtXENl4d0yV3i8nPK1O2WEHwloaM.pdf	supabase	application/pdf	107792	131	2026-08-30 02:54:05	2026-08-30 02:54:05
269	83	officers_list	KEYTAKEAWAYS.pdf	attachments/organization_registration/83/bCUAiMruynxDzUJHKzCNmgj7gqs5aaVE8orTPeQd.pdf	supabase	application/pdf	107792	131	2026-08-30 02:54:05	2026-08-30 02:54:05
270	83	dean_endorsement_letter	KEYTAKEAWAYS.pdf	attachments/organization_registration/83/Ptjvh32cvPRhUX7PKRxTb9T29RpcSwbRpSfN66eg.pdf	supabase	application/pdf	107792	131	2026-08-30 02:54:05	2026-08-30 02:54:05
271	83	proposed_projects_budget	KEYTAKEAWAYS.pdf	attachments/organization_registration/83/NAnS39SnMlz2BV4q9C2uryAlUYHG1QaKoCv7lQUM.pdf	supabase	application/pdf	107792	131	2026-08-30 02:54:06	2026-08-30 02:54:06
272	84	letter_of_intent	KEYTAKEAWAYS.pdf	attachments/organization_registration/84/IG8MSwApotzuadTxj5bKsszGGsMoRfAeKrto6dAI.pdf	supabase	application/pdf	107792	131	2026-08-30 03:46:43	2026-08-30 03:46:43
274	84	by_laws	KEYTAKEAWAYS.pdf	attachments/organization_registration/84/5Z8UFPDEVx0Z1oBewUgXm3XZ7xyMQihddfDqPd2W.pdf	supabase	application/pdf	107792	131	2026-08-30 03:46:43	2026-08-30 03:46:43
276	84	dean_endorsement_letter	KEYTAKEAWAYS.pdf	attachments/organization_registration/84/0iCYaqBUv6QTA71KAaW8vQSdMmVuU1stcK6t8Msn.pdf	supabase	application/pdf	107792	131	2026-08-30 03:46:43	2026-08-30 03:46:43
278	84	application_form	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/84/1NvclFknDyjPoWXy5xcESZVKrQgGkO537Xo2cCwf.pdf	supabase	application/pdf	121241	131	2026-08-30 03:51:11	2026-08-30 03:51:11
279	84	officers_list	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/84/F2L0bmJNhkkmTvkLJPDhJVDZOZWgHhk3tuNh0I9q.pdf	supabase	application/pdf	121241	131	2026-08-30 03:51:11	2026-08-30 03:51:11
280	84	proposed_projects_budget	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/84/zQR3IRo9hxD5lDgDHteCT6Bfjr48FjjkGrOwIJaL.pdf	supabase	application/pdf	121241	131	2026-08-30 03:51:12	2026-08-30 03:51:12
281	85	letter_of_intent	KEYTAKEAWAYS.pdf	attachments/organization_registration/85/1m7j0GzD0kP44cUkzQYLRjJEHvArTT5Oqo8yrqPb.pdf	supabase	application/pdf	107792	133	2026-08-30 04:13:47	2026-08-30 04:13:47
282	85	application_form	KEYTAKEAWAYS.pdf	attachments/organization_registration/85/j2j8CEHDA5wcErzkDKAu8o3qwytCrK1ohbaYT0mC.pdf	supabase	application/pdf	107792	133	2026-08-30 04:13:47	2026-08-30 04:13:47
283	85	by_laws	KEYTAKEAWAYS.pdf	attachments/organization_registration/85/1SkEUpkhvHgRVJ0JxcJzLiTvkzLDrU1yQArXiUxZ.pdf	supabase	application/pdf	107792	133	2026-08-30 04:13:47	2026-08-30 04:13:47
284	85	officers_list	KEYTAKEAWAYS.pdf	attachments/organization_registration/85/JFuW5gQ1mIfOLDiJaOFH2rpiy6p1JLcIUhk7lopU.pdf	supabase	application/pdf	107792	133	2026-08-30 04:13:47	2026-08-30 04:13:47
285	85	dean_endorsement_letter	KEYTAKEAWAYS.pdf	attachments/organization_registration/85/6EU25TAM7tkGwH9kmqkJCFWeHIevHCwBYOk46GCJ.pdf	supabase	application/pdf	107792	133	2026-08-30 04:13:48	2026-08-30 04:13:48
286	85	proposed_projects_budget	KEYTAKEAWAYS.pdf	attachments/organization_registration/85/y22CM8wXMGUdBLZbFTHGLpNJphZ5S5zwTT9GpAN5.pdf	supabase	application/pdf	107792	133	2026-08-30 04:13:48	2026-08-30 04:13:48
287	86	letter_of_intent	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/86/NivsjQWBcWyFMFeudlcOuCVTOSkeLHKtYZUlGivx.pdf	supabase	application/pdf	121241	135	2026-08-30 04:16:09	2026-08-30 04:16:09
288	86	application_form	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/86/pcp93cDz25dBMSk77u5cg9K7ZxRRzZVHjprtobaa.pdf	supabase	application/pdf	121241	135	2026-08-30 04:16:09	2026-08-30 04:16:09
289	86	by_laws	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/86/PRGq2dZvOtd3lYXGPo34fispCZS8QHgIAkZ6sH7U.pdf	supabase	application/pdf	121241	135	2026-08-30 04:16:09	2026-08-30 04:16:09
290	86	officers_list	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/86/ZHyT26qxLfTf9k12zMKpUzn65OWZAzcEz5rDo2yr.pdf	supabase	application/pdf	121241	135	2026-08-30 04:16:09	2026-08-30 04:16:09
291	86	dean_endorsement_letter	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/86/BV3OsuXR9wXT2saMGz0wlo20NLZWR8OCebrxj8XB.pdf	supabase	application/pdf	121241	135	2026-08-30 04:16:09	2026-08-30 04:16:09
292	86	proposed_projects_budget	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/86/obyfPR1XUtx0bNW9AcnYRa5ummYSkCGHQ9Jmc1Mq.pdf	supabase	application/pdf	121241	135	2026-08-30 04:16:09	2026-08-30 04:16:09
299	87	application_form	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/87/jTxWQbmPikDet28Yt5Kj3isViuudrKIwk1u9GSuD.pdf	supabase	application/pdf	121241	137	2026-08-30 08:42:33	2026-08-30 08:42:33
300	87	officers_list	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/87/aMRURd1jH9jy9Hk1kRB0z04SeAAlDGd3Bc9nv2uE.pdf	supabase	application/pdf	121241	137	2026-08-30 08:42:33	2026-08-30 08:42:33
301	87	proposed_projects_budget	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/87/SJW49xqzy5xvaprCPyO1EaJjcmUln9vnrQtmz1RO.pdf	supabase	application/pdf	121241	137	2026-08-30 08:42:33	2026-08-30 08:42:33
302	87	letter_of_intent	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/87/thi2K5pY9NZYTYS8UIYAtnhQOcFbIxgT4orx8zkb.pdf	supabase	application/pdf	121241	137	2026-08-30 08:44:26	2026-08-30 08:44:26
303	87	by_laws	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/87/U0zPsQeSR8YqA58xJHAQwGchnYC2YbFIafAcGJuX.pdf	supabase	application/pdf	121241	137	2026-08-30 08:44:26	2026-08-30 08:44:26
304	87	dean_endorsement_letter	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/87/8WZXmoBfYP6ntzHKaHCTGVxyvnSHH1WO7qL0uXwy.pdf	supabase	application/pdf	121241	137	2026-08-30 08:44:27	2026-08-30 08:44:27
305	88	letter_of_intent	KEYTAKEAWAYS.pdf	attachments/organization_registration/88/dlbT6PfryUnM46o5EAKeniRU8AsiQ839z9lE4T1z.pdf	supabase	application/pdf	107792	138	2026-08-30 14:41:17	2026-08-30 14:41:17
306	88	application_form	KEYTAKEAWAYS.pdf	attachments/organization_registration/88/f3WpITigAxve2bHbNmHK3bRHn5SwtWohZMbfgHOb.pdf	supabase	application/pdf	107792	138	2026-08-30 14:41:17	2026-08-30 14:41:17
307	88	by_laws	KEYTAKEAWAYS.pdf	attachments/organization_registration/88/RgRVPyFu9p7YrRBq1ceLMZFVAWlV2Cqu6gc0awrA.pdf	supabase	application/pdf	107792	138	2026-08-30 14:41:19	2026-08-30 14:41:19
308	88	officers_list	KEYTAKEAWAYS.pdf	attachments/organization_registration/88/I6LT3jpQ9K2MbbXYj6ynPBjatz8fiwfouT7wCxJn.pdf	supabase	application/pdf	107792	138	2026-08-30 14:41:19	2026-08-30 14:41:19
309	88	dean_endorsement_letter	KEYTAKEAWAYS.pdf	attachments/organization_registration/88/4j4NbeZW5HjnxFXPuTyPCUArZ9cOpfZl9r24hGcx.pdf	supabase	application/pdf	107792	138	2026-08-30 14:41:20	2026-08-30 14:41:20
310	88	proposed_projects_budget	KEYTAKEAWAYS.pdf	attachments/organization_registration/88/PM9khmcYpuao0don42mcEGudwInHJG1gBUMO7Dte.pdf	supabase	application/pdf	107792	138	2026-08-30 14:41:20	2026-08-30 14:41:20
311	89	letter_of_intent	KEYTAKEAWAYS.pdf	attachments/organization_renewal/89/V4U7uIxdsFWO3zVUVYI6td1fYvOl9rRuj0UR6l9P.pdf	supabase	application/pdf	107792	138	2026-08-30 14:53:32	2026-08-30 14:53:32
312	89	application_form	KEYTAKEAWAYS.pdf	attachments/organization_renewal/89/PAJ1zwaunsg8c7DcAQ8Uxbt76Uuh2DlFBpVv208t.pdf	supabase	application/pdf	107792	138	2026-08-30 14:53:32	2026-08-30 14:53:32
313	89	by_laws	KEYTAKEAWAYS.pdf	attachments/organization_renewal/89/WVgntfiLYD7iS9frys3lHiUVrzpaCZrDkCYjCJSw.pdf	supabase	application/pdf	107792	138	2026-08-30 14:53:32	2026-08-30 14:53:32
314	89	officers_list	KEYTAKEAWAYS.pdf	attachments/organization_renewal/89/nSq1FTMCnryyMJRAoKGEOh8yGOLsJUVPQO1fLpPb.pdf	supabase	application/pdf	107792	138	2026-08-30 14:53:32	2026-08-30 14:53:32
315	89	dean_endorsement_letter	KEYTAKEAWAYS.pdf	attachments/organization_renewal/89/NMA0T4zLlwak0LMFT3LLCxK6oO9mKo8Pzydk9FoJ.pdf	supabase	application/pdf	107792	138	2026-08-30 14:53:32	2026-08-30 14:53:32
316	89	proposed_projects_budget	KEYTAKEAWAYS.pdf	attachments/organization_renewal/89/o886FLSha3mMwlZBlnP6ZkZy8pQP1j0xArJN9ktn.pdf	supabase	application/pdf	107792	138	2026-08-30 14:53:33	2026-08-30 14:53:33
317	89	past_projects_list	KEYTAKEAWAYS.pdf	attachments/organization_renewal/89/FxVBMa64ZPHPEOuN53PwtmDUroT7gk0FtDCycyo9.pdf	supabase	application/pdf	107792	138	2026-08-30 14:53:33	2026-08-30 14:53:33
318	89	financial_statement	KEYTAKEAWAYS.pdf	attachments/organization_renewal/89/bthyWLMbIdVaUGdGwQEEDknXjODrUJe9amzh8cxY.pdf	supabase	application/pdf	107792	138	2026-08-30 14:53:33	2026-08-30 14:53:33
319	89	evaluation_summary	KEYTAKEAWAYS.pdf	attachments/organization_renewal/89/mMbgTDqfB2b7HIVdEGkq4xHgOvivqTI6CTfVPYz1.pdf	supabase	application/pdf	107792	138	2026-08-30 14:53:33	2026-08-30 14:53:33
260	82	letter_of_intent	Week 5 - Activity 5 (ITELEC5L) e-Commerce Security.pdf	attachments/organization_registration/82/HzIjaXU203L9qGW4cHwANzRVdY6x3UVL8fzkAGwB.pdf	supabase	application/pdf	121241	\N	2026-08-29 14:15:42	2026-08-29 14:15:42
261	82	application_form	KEYTAKEAWAYS.pdf	attachments/organization_registration/82/0JjCEesVNV57tKbWj2H9iMm27OZ0LS9ugYdTndky.pdf	supabase	application/pdf	107792	\N	2026-08-29 14:15:43	2026-08-29 14:15:43
262	82	by_laws	KEYTAKEAWAYS.pdf	attachments/organization_registration/82/EDs1F8xV6cpyGEwOQygHqcAOvDDShJKrdDWNfszL.pdf	supabase	application/pdf	107792	\N	2026-08-29 14:15:44	2026-08-29 14:15:44
263	82	officers_list	KEYTAKEAWAYS.pdf	attachments/organization_registration/82/rbGT8ljlwCCGikoNNzSMkw9iKRSfDixySchiovXS.pdf	supabase	application/pdf	107792	\N	2026-08-29 14:15:45	2026-08-29 14:15:45
264	82	dean_endorsement_letter	KEYTAKEAWAYS.pdf	attachments/organization_registration/82/qNeFECIveAMLfxrAwYCvkHfwWKHZhyKBLffbvD8K.pdf	supabase	application/pdf	107792	\N	2026-08-29 14:15:46	2026-08-29 14:15:46
265	82	proposed_projects_budget	KEYTAKEAWAYS.pdf	attachments/organization_registration/82/YWNBRJeAGZWNnJFKzNE5N57rukZMul5ypkpRZgJJ.pdf	supabase	application/pdf	107792	\N	2026-08-29 14:15:48	2026-08-29 14:15:48
320	90	letter_of_intent	KEYTAKEAWAYS.pdf	attachments/organization_registration/90/Wu9lVGca7VPFXLbdd7UyOznGOL56CaFxx3hYIe0o.pdf	supabase	application/pdf	107792	139	2026-09-03 08:06:36	2026-09-03 08:06:36
321	90	application_form	KEYTAKEAWAYS.pdf	attachments/organization_registration/90/qFBRUsTXdLL25vdSvhepjlxrvB0mwe9ZTAfAG4Hp.pdf	supabase	application/pdf	107792	139	2026-09-03 08:06:36	2026-09-03 08:06:36
322	90	by_laws	KEYTAKEAWAYS.pdf	attachments/organization_registration/90/TqkiIilu1tXmvfRcfbXbvQjcvcIJGhZl34VZmTwt.pdf	supabase	application/pdf	107792	139	2026-09-03 08:06:37	2026-09-03 08:06:37
323	90	officers_list	KEYTAKEAWAYS.pdf	attachments/organization_registration/90/FUQjaSZ1LDJ7NLjRNYR6iOiIvF3ziYUymMQUizoQ.pdf	supabase	application/pdf	107792	139	2026-09-03 08:06:37	2026-09-03 08:06:37
324	90	dean_endorsement_letter	KEYTAKEAWAYS.pdf	attachments/organization_registration/90/BVwtwhBbmEvUsb0BkWXLmrU43GCXX104O6DsftNk.pdf	supabase	application/pdf	107792	139	2026-09-03 08:06:37	2026-09-03 08:06:37
325	90	proposed_projects_budget	KEYTAKEAWAYS.pdf	attachments/organization_registration/90/TAY6hUFGbuYZZzaeDJvhQMvDBoRxzmoJERp4e3ag.pdf	supabase	application/pdf	107792	139	2026-09-03 08:06:37	2026-09-03 08:06:37
326	91	letter_of_intent	KEYTAKEAWAYS.pdf	attachments/organization_renewal/91/GLpG420uefOoWxCxNkEtICPF5yx8fplFIOcryqZK.pdf	supabase	application/pdf	107792	139	2026-09-03 08:08:01	2026-09-03 08:08:01
327	91	application_form	KEYTAKEAWAYS.pdf	attachments/organization_renewal/91/4RXLt9MAPl3Dve0NZqQhEjff0tTh4WNGGXrwZQNc.pdf	supabase	application/pdf	107792	139	2026-09-03 08:08:01	2026-09-03 08:08:01
328	91	by_laws	KEYTAKEAWAYS.pdf	attachments/organization_renewal/91/UNg0EwxxQNf2ADTL14Lv8BkxtBQUyOUrwyTUMyVo.pdf	supabase	application/pdf	107792	139	2026-09-03 08:08:01	2026-09-03 08:08:01
329	91	officers_list	KEYTAKEAWAYS.pdf	attachments/organization_renewal/91/KvEMPbW7CKxnL6A6MrdvBoKWeBiUEMObaOmvHX5q.pdf	supabase	application/pdf	107792	139	2026-09-03 08:08:02	2026-09-03 08:08:02
330	91	dean_endorsement_letter	KEYTAKEAWAYS.pdf	attachments/organization_renewal/91/V5Jqnb6kD1zRrjPUGlEI3q1tsHeEH8XdY2nQ2q8v.pdf	supabase	application/pdf	107792	139	2026-09-03 08:08:02	2026-09-03 08:08:02
331	91	proposed_projects_budget	KEYTAKEAWAYS.pdf	attachments/organization_renewal/91/VbYwSSVIUrnywng3ZfxNS6zLTmpTNbTDdJUfqu9h.pdf	supabase	application/pdf	107792	139	2026-09-03 08:08:02	2026-09-03 08:08:02
332	91	past_projects_list	KEYTAKEAWAYS.pdf	attachments/organization_renewal/91/0JbMxYe2ykG3zyN8SwbMZFgqfxVX1rRxwcSPbd2A.pdf	supabase	application/pdf	107792	139	2026-09-03 08:08:02	2026-09-03 08:08:02
333	91	financial_statement	KEYTAKEAWAYS.pdf	attachments/organization_renewal/91/fiw7wP2WiqLkRclE154tJXh5xXz7DsqX22meV3dH.pdf	supabase	application/pdf	107792	139	2026-09-03 08:08:03	2026-09-03 08:08:03
334	91	evaluation_summary	KEYTAKEAWAYS.pdf	attachments/organization_renewal/91/hrHcI9qaIoa35PYoDWc54xa7YO5B2S2xOL97YD9l.pdf	supabase	application/pdf	107792	139	2026-09-03 08:08:03	2026-09-03 08:08:03
335	92	letter_of_intent	KEYTAKEAWAYS.pdf	attachments/organization_renewal/92/0J2weZYNcWb3QP7PRO9efjUtnc0kCNSur7bIVRwS.pdf	supabase	application/pdf	107792	139	2026-09-03 08:10:22	2026-09-03 08:10:22
336	92	application_form	KEYTAKEAWAYS.pdf	attachments/organization_renewal/92/CGuni9MCCGvT9TxpGJqucNdYzPjHUx0Erl3YDW0I.pdf	supabase	application/pdf	107792	139	2026-09-03 08:10:22	2026-09-03 08:10:22
337	92	by_laws	KEYTAKEAWAYS.pdf	attachments/organization_renewal/92/CcMFkLlg4zM9b4gbcTxhggQ60H5Cm7pGen1LIBDB.pdf	supabase	application/pdf	107792	139	2026-09-03 08:10:22	2026-09-03 08:10:22
338	92	officers_list	KEYTAKEAWAYS.pdf	attachments/organization_renewal/92/legmxcZDLad6IL1URGVkIuDPAUy12woC9y8xp8Zx.pdf	supabase	application/pdf	107792	139	2026-09-03 08:10:22	2026-09-03 08:10:22
339	92	dean_endorsement_letter	KEYTAKEAWAYS.pdf	attachments/organization_renewal/92/7veg1lncYCYzA70jYJdQNomDNkaCnLA5GKn6vumN.pdf	supabase	application/pdf	107792	139	2026-09-03 08:10:22	2026-09-03 08:10:22
340	92	proposed_projects_budget	KEYTAKEAWAYS.pdf	attachments/organization_renewal/92/XiN634eniySm3aQxzeS7A0CZI7XulJM8U6oU7kFZ.pdf	supabase	application/pdf	107792	139	2026-09-03 08:10:22	2026-09-03 08:10:22
341	92	past_projects_list	KEYTAKEAWAYS.pdf	attachments/organization_renewal/92/Gc1ZnqFefIyfXIJkgJBE1GoOcYdmxnLJjNIga0OV.pdf	supabase	application/pdf	107792	139	2026-09-03 08:10:22	2026-09-03 08:10:22
342	92	financial_statement	KEYTAKEAWAYS.pdf	attachments/organization_renewal/92/Z6SZ2ykT5wpE5nO5g4YdE45fgAOqNwfJEhv2Ct3Q.pdf	supabase	application/pdf	107792	139	2026-09-03 08:10:23	2026-09-03 08:10:23
343	92	evaluation_summary	KEYTAKEAWAYS.pdf	attachments/organization_renewal/92/7PyqXXiTrDNLnnWjsMkopF52zwMQT9T5dzkXBOlS.pdf	supabase	application/pdf	107792	139	2026-09-03 08:10:23	2026-09-03 08:10:23
344	102	resume_of_resource_person	KEYTAKEAWAYS.pdf	attachments/activity_proposal/102/ZEdo7wpHzPcGO2T0jONS2TChc5OsduAwIL0fyfjf.pdf	supabase	application/pdf	107792	139	2026-09-05 11:01:42	2026-09-05 11:01:42
345	104	letter_of_intent	KEYTAKEAWAYS.pdf	attachments/organization_registration/104/FlrAdpOikTaaxtd2KSn42XPqIp6AWWeD9CCFfBrz.pdf	supabase	application/pdf	107792	141	2026-09-05 13:39:54	2026-09-05 13:39:54
346	104	application_form	KEYTAKEAWAYS.pdf	attachments/organization_registration/104/tur31tmKRvVfsHhOMzGhJ0Ix3mvBy7OxMOO79QHQ.pdf	supabase	application/pdf	107792	141	2026-09-05 13:39:55	2026-09-05 13:39:55
347	104	by_laws	KEYTAKEAWAYS.pdf	attachments/organization_registration/104/RoqEezctCESFxww3LwpgbyT2D5Tn93MuN9kaaFeC.pdf	supabase	application/pdf	107792	141	2026-09-05 13:39:55	2026-09-05 13:39:55
348	104	officers_list	KEYTAKEAWAYS.pdf	attachments/organization_registration/104/uyk7gpzk0y9SeRN2ofDrkm76SvvTfo5uhdN3ljop.pdf	supabase	application/pdf	107792	141	2026-09-05 13:39:55	2026-09-05 13:39:55
349	104	dean_endorsement_letter	KEYTAKEAWAYS.pdf	attachments/organization_registration/104/pHrJtWBhOVifwnpxCMyEV24avPHdqJAdH7MIpFy0.pdf	supabase	application/pdf	107792	141	2026-09-05 13:39:55	2026-09-05 13:39:55
350	104	proposed_projects_budget	KEYTAKEAWAYS.pdf	attachments/organization_registration/104/7aKFpJUlJezQ2MYgnZV0t7VYOsbLs73xrwjljnxv.pdf	supabase	application/pdf	107792	141	2026-09-05 13:39:55	2026-09-05 13:39:55
\.


--
-- Data for Name: document_step_approvals; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.document_step_approvals (id, document_id, workflow_step_id, step_position, user_id, created_at, updated_at) FROM stdin;
62	35	1	1	21	2026-08-21 05:33:21	2026-08-21 05:33:21
63	35	1	1	22	2026-08-21 05:33:33	2026-08-21 05:33:33
64	36	1	1	21	2026-08-21 05:34:47	2026-08-21 05:34:47
65	36	1	1	22	2026-08-21 05:34:59	2026-08-21 05:34:59
66	37	1	1	21	2026-08-21 05:36:13	2026-08-21 05:36:13
67	37	1	1	22	2026-08-21 05:36:25	2026-08-21 05:36:25
68	38	1	1	21	2026-08-21 05:37:39	2026-08-21 05:37:39
69	38	1	1	22	2026-08-21 05:37:51	2026-08-21 05:37:51
70	39	1	1	21	2026-08-21 05:39:06	2026-08-21 05:39:06
71	39	1	1	22	2026-08-21 05:39:18	2026-08-21 05:39:18
72	40	1	1	21	2026-08-21 05:40:32	2026-08-21 05:40:32
73	40	1	1	22	2026-08-21 05:40:44	2026-08-21 05:40:44
74	41	1	1	21	2026-08-21 05:41:59	2026-08-21 05:41:59
75	41	1	1	22	2026-08-21 05:42:11	2026-08-21 05:42:11
76	42	1	1	21	2026-08-21 05:43:26	2026-08-21 05:43:26
77	42	1	1	22	2026-08-21 05:43:37	2026-08-21 05:43:37
78	44	1	1	21	2026-08-21 05:45:13	2026-08-21 05:45:13
79	48	2	1	21	2026-08-21 05:48:14	2026-08-21 05:48:14
80	50	2	1	21	2026-08-21 05:50:22	2026-08-21 05:50:22
81	50	2	1	22	2026-08-21 05:50:30	2026-08-21 05:50:30
82	52	3	1	21	2026-08-21 05:52:34	2026-08-21 05:52:34
83	52	3	1	22	2026-08-21 05:52:41	2026-08-21 05:52:41
84	53	3	1	21	2026-08-21 05:53:42	2026-08-21 05:53:42
85	53	3	1	22	2026-08-21 05:53:49	2026-08-21 05:53:49
86	54	3	1	21	2026-08-21 05:54:46	2026-08-21 05:54:46
87	54	3	1	22	2026-08-21 05:54:53	2026-08-21 05:54:53
88	56	3	1	21	2026-08-21 05:56:36	2026-08-21 05:56:36
89	56	3	1	22	2026-08-21 05:56:44	2026-08-21 05:56:44
90	57	3	1	21	2026-08-21 05:57:40	2026-08-21 05:57:40
91	57	3	1	22	2026-08-21 05:57:47	2026-08-21 05:57:47
92	58	3	1	21	2026-08-21 05:58:48	2026-08-21 05:58:48
93	58	3	1	22	2026-08-21 05:58:56	2026-08-21 05:58:56
94	59	3	1	21	2026-08-21 05:59:52	2026-08-21 05:59:52
95	59	3	1	22	2026-08-21 05:59:59	2026-08-21 05:59:59
96	61	12	1	21	2026-08-21 06:01:12	2026-08-21 06:01:12
97	62	5	1	51	2026-08-21 06:01:46	2026-08-21 06:01:46
98	63	19	1	54	2026-08-21 06:02:52	2026-08-21 06:02:52
99	63	20	2	26	2026-08-21 06:03:16	2026-08-21 06:03:16
100	63	21	3	21	2026-08-21 06:03:54	2026-08-21 06:03:54
101	63	21	3	22	2026-08-21 06:04:02	2026-08-21 06:04:02
102	63	22	4	23	2026-08-21 06:04:24	2026-08-21 06:04:24
103	63	23	5	24	2026-08-21 06:04:46	2026-08-21 06:04:46
104	63	24	6	25	2026-08-21 06:05:09	2026-08-21 06:05:09
105	65	5	1	48	2026-08-21 06:06:54	2026-08-21 06:06:54
106	65	6	2	28	2026-08-21 06:07:17	2026-08-21 06:07:17
107	65	7	3	27	2026-08-21 06:07:41	2026-08-21 06:07:41
108	65	8	4	21	2026-08-21 06:08:19	2026-08-21 06:08:19
109	65	8	4	22	2026-08-21 06:08:27	2026-08-21 06:08:27
110	65	9	5	23	2026-08-21 06:08:49	2026-08-21 06:08:49
111	65	10	6	24	2026-08-21 06:09:11	2026-08-21 06:09:11
112	65	11	7	25	2026-08-21 06:09:33	2026-08-21 06:09:33
113	66	12	1	21	2026-08-21 06:10:46	2026-08-21 06:10:46
114	66	12	1	22	2026-08-21 06:10:54	2026-08-21 06:10:54
115	66	13	2	85	2026-08-21 06:11:18	2026-08-21 06:11:18
116	66	14	3	30	2026-08-21 06:11:41	2026-08-21 06:11:41
117	66	15	4	27	2026-08-21 06:12:05	2026-08-21 06:12:05
118	66	16	5	23	2026-08-21 06:12:27	2026-08-21 06:12:27
119	66	17	6	24	2026-08-21 06:12:49	2026-08-21 06:12:49
120	66	18	7	25	2026-08-21 06:13:11	2026-08-21 06:13:11
121	67	4	1	21	2026-08-21 06:14:19	2026-08-21 06:14:19
122	67	4	1	22	2026-08-21 06:14:27	2026-08-21 06:14:27
123	68	4	1	21	2026-08-21 06:15:25	2026-08-21 06:15:25
124	70	3	1	21	2026-08-23 12:59:22	2026-08-23 12:59:22
125	71	1	1	22	2026-08-24 06:16:47	2026-08-24 06:16:47
126	71	1	1	41	2026-08-24 06:33:10	2026-08-24 06:33:10
127	72	2	1	41	2026-08-24 06:35:37	2026-08-24 06:35:37
128	72	2	1	22	2026-08-24 06:38:06	2026-08-24 06:38:06
130	74	1	1	42	2026-08-24 09:30:54	2026-08-24 09:30:54
131	75	3	1	41	2026-08-24 12:30:53	2026-08-24 12:30:53
132	75	3	1	42	2026-08-24 12:36:39	2026-08-24 12:36:39
133	79	12	1	41	2026-08-24 13:57:59	2026-08-24 13:57:59
134	80	5	1	40	2026-08-24 16:25:33	2026-08-24 16:25:33
135	80	6	2	38	2026-08-24 16:38:00	2026-08-24 16:38:00
136	80	7	3	36	2026-08-24 16:41:23	2026-08-24 16:41:23
137	80	8	4	41	2026-08-24 16:45:20	2026-08-24 16:45:20
138	80	8	4	42	2026-08-24 16:47:50	2026-08-24 16:47:50
139	80	9	5	23	2026-08-24 16:51:14	2026-08-24 16:51:14
140	80	10	6	24	2026-08-24 16:53:00	2026-08-24 16:53:00
141	80	11	7	25	2026-08-24 16:54:33	2026-08-24 16:54:33
142	81	4	1	21	2026-08-24 17:07:04	2026-08-24 17:07:04
143	81	4	1	22	2026-08-24 17:08:36	2026-08-24 17:08:36
144	82	1	1	42	2026-08-29 14:18:34	2026-08-29 14:18:34
145	82	1	1	41	2026-08-29 14:20:15	2026-08-29 14:20:15
146	84	1	1	42	2026-08-30 03:52:15	2026-08-30 03:52:15
147	84	1	1	41	2026-08-30 03:56:43	2026-08-30 03:56:43
148	85	1	1	41	2026-08-30 04:16:36	2026-08-30 04:16:36
149	85	1	1	42	2026-08-30 04:17:04	2026-08-30 04:17:04
151	87	1	1	42	2026-08-30 08:47:51	2026-08-30 08:47:51
152	87	1	1	41	2026-08-30 14:35:14	2026-08-30 14:35:14
154	88	1	1	42	2026-08-30 14:42:41	2026-08-30 14:42:41
155	88	1	1	41	2026-08-30 14:46:36	2026-08-30 14:46:36
156	89	2	1	41	2026-08-30 14:56:04	2026-08-30 14:56:04
157	89	2	1	42	2026-08-30 14:56:27	2026-08-30 14:56:27
158	90	1	1	41	2026-09-03 08:06:50	2026-09-03 08:06:50
159	90	1	1	42	2026-09-03 08:07:09	2026-09-03 08:07:09
160	92	2	1	42	2026-09-03 08:10:55	2026-09-03 08:10:55
161	92	2	1	41	2026-09-03 08:11:10	2026-09-03 08:11:10
162	96	3	1	41	2026-09-03 08:30:50	2026-09-03 08:30:50
163	96	3	1	42	2026-09-03 08:31:42	2026-09-03 08:31:42
164	95	3	1	41	2026-09-03 08:58:39	2026-09-03 08:58:39
165	95	3	1	42	2026-09-03 08:59:03	2026-09-03 08:59:03
166	98	3	1	42	2026-09-03 09:04:32	2026-09-03 09:04:32
167	98	3	1	41	2026-09-03 09:05:27	2026-09-03 09:05:27
168	103	5	1	140	2026-09-03 10:00:20	2026-09-03 10:00:20
169	103	6	2	29	2026-09-03 10:03:26	2026-09-03 10:03:26
170	103	7	3	27	2026-09-03 10:05:48	2026-09-03 10:05:48
171	103	8	4	41	2026-09-03 10:08:30	2026-09-03 10:08:30
172	103	8	4	42	2026-09-03 10:09:34	2026-09-03 10:09:34
173	102	5	1	140	2026-09-05 11:03:35	2026-09-05 11:03:35
174	102	6	2	29	2026-09-05 11:06:17	2026-09-05 11:06:17
175	102	7	3	27	2026-09-05 11:06:41	2026-09-05 11:06:41
176	102	8	4	41	2026-09-05 11:07:12	2026-09-05 11:07:12
177	102	8	4	42	2026-09-05 11:07:43	2026-09-05 11:07:43
178	102	9	5	23	2026-09-05 13:20:46	2026-09-05 13:20:46
179	102	10	6	24	2026-09-05 13:21:15	2026-09-05 13:21:15
180	102	11	7	25	2026-09-05 13:21:59	2026-09-05 13:21:59
181	104	1	1	41	2026-09-05 13:40:07	2026-09-05 13:40:07
182	104	1	1	42	2026-09-05 13:41:49	2026-09-05 13:41:49
\.


--
-- Data for Name: document_transitions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.document_transitions (id, document_id, actor_id, action, from_status, to_status, step_position, comment, created_at, flagged_sections, section_comments, field_changes) FROM stdin;
137	35	91	submitted	draft	in_review	1	\N	2026-08-21 05:32:51	\N	\N	\N
138	35	21	approved	in_review	in_review	1	\N	2026-08-21 05:33:22	\N	\N	\N
139	35	22	approved	in_review	in_review	1	\N	2026-08-21 05:33:34	\N	\N	\N
140	35	22	completed	in_review	approved	1	\N	2026-08-21 05:33:38	\N	\N	\N
141	36	92	submitted	draft	in_review	1	\N	2026-08-21 05:34:17	\N	\N	\N
142	36	21	approved	in_review	in_review	1	\N	2026-08-21 05:34:48	\N	\N	\N
143	36	22	approved	in_review	in_review	1	\N	2026-08-21 05:35:00	\N	\N	\N
144	36	22	completed	in_review	approved	1	\N	2026-08-21 05:35:04	\N	\N	\N
145	37	93	submitted	draft	in_review	1	\N	2026-08-21 05:35:43	\N	\N	\N
146	37	21	approved	in_review	in_review	1	\N	2026-08-21 05:36:14	\N	\N	\N
147	37	22	approved	in_review	in_review	1	\N	2026-08-21 05:36:26	\N	\N	\N
148	37	22	completed	in_review	approved	1	\N	2026-08-21 05:36:30	\N	\N	\N
149	38	94	submitted	draft	in_review	1	\N	2026-08-21 05:37:09	\N	\N	\N
150	38	21	approved	in_review	in_review	1	\N	2026-08-21 05:37:40	\N	\N	\N
151	38	22	approved	in_review	in_review	1	\N	2026-08-21 05:37:52	\N	\N	\N
152	38	22	completed	in_review	approved	1	\N	2026-08-21 05:37:56	\N	\N	\N
153	39	95	submitted	draft	in_review	1	\N	2026-08-21 05:38:35	\N	\N	\N
154	39	21	approved	in_review	in_review	1	\N	2026-08-21 05:39:07	\N	\N	\N
155	39	22	approved	in_review	in_review	1	\N	2026-08-21 05:39:18	\N	\N	\N
156	39	22	completed	in_review	approved	1	\N	2026-08-21 05:39:23	\N	\N	\N
157	40	96	submitted	draft	in_review	1	\N	2026-08-21 05:40:02	\N	\N	\N
158	40	21	approved	in_review	in_review	1	\N	2026-08-21 05:40:33	\N	\N	\N
159	40	22	approved	in_review	in_review	1	\N	2026-08-21 05:40:45	\N	\N	\N
160	40	22	completed	in_review	approved	1	\N	2026-08-21 05:40:49	\N	\N	\N
161	41	97	submitted	draft	in_review	1	\N	2026-08-21 05:41:28	\N	\N	\N
162	41	21	approved	in_review	in_review	1	\N	2026-08-21 05:42:00	\N	\N	\N
163	41	22	approved	in_review	in_review	1	\N	2026-08-21 05:42:12	\N	\N	\N
164	41	22	completed	in_review	approved	1	\N	2026-08-21 05:42:16	\N	\N	\N
165	42	98	submitted	draft	in_review	1	\N	2026-08-21 05:42:55	\N	\N	\N
166	42	21	approved	in_review	in_review	1	\N	2026-08-21 05:43:26	\N	\N	\N
167	42	22	approved	in_review	in_review	1	\N	2026-08-21 05:43:38	\N	\N	\N
168	42	22	completed	in_review	approved	1	\N	2026-08-21 05:43:42	\N	\N	\N
169	44	103	submitted	draft	in_review	1	\N	2026-08-21 05:44:42	\N	\N	\N
170	44	21	approved	in_review	in_review	1	\N	2026-08-21 05:45:14	\N	\N	\N
171	45	104	submitted	draft	in_review	1	\N	2026-08-21 05:45:38	\N	\N	\N
172	45	22	returned	in_review	returned	1	The chosen adviser is already assigned to another organization. Please pick a different available adviser.	2026-08-21 05:46:08	["adviser_selection","attachments"]	{"adviser_selection":"This adviser is already bound elsewhere \\u2014 choose someone else."}	\N
173	46	105	submitted	draft	in_review	1	\N	2026-08-21 05:46:35	\N	\N	\N
174	46	21	rejected	in_review	rejected	1	Incomplete and inconsistent supporting documents. Please file a new registration once ready.	2026-08-21 05:47:04	\N	\N	\N
175	48	92	submitted	draft	in_review	1	\N	2026-08-21 05:47:46	\N	\N	\N
176	48	21	approved	in_review	in_review	1	\N	2026-08-21 05:48:15	\N	\N	\N
177	49	93	submitted	draft	in_review	1	\N	2026-08-21 05:48:45	\N	\N	\N
178	49	21	returned	in_review	returned	1	Please update the list of past projects — the current one is missing this year's activities.	2026-08-21 05:49:15	["past_projects_list","evaluation_summary"]	\N	\N
179	50	96	submitted	draft	in_review	1	\N	2026-08-21 05:49:54	\N	\N	\N
180	50	21	approved	in_review	in_review	1	\N	2026-08-21 05:50:23	\N	\N	\N
181	50	22	approved	in_review	in_review	1	\N	2026-08-21 05:50:30	\N	\N	\N
182	50	22	completed	in_review	approved	1	\N	2026-08-21 05:50:35	\N	\N	\N
183	51	97	submitted	draft	in_review	1	\N	2026-08-21 05:51:13	\N	\N	\N
184	51	22	rejected	in_review	rejected	1	Financial statement does not reconcile with the reported past projects. Please resubmit as a new renewal once corrected.	2026-08-21 05:51:42	\N	\N	\N
185	52	91	submitted	draft	in_review	1	\N	2026-08-21 05:52:02	\N	\N	\N
186	52	21	approved	in_review	in_review	1	\N	2026-08-21 05:52:34	\N	\N	\N
187	52	22	approved	in_review	in_review	1	\N	2026-08-21 05:52:42	\N	\N	\N
188	52	22	completed	in_review	approved	1	\N	2026-08-21 05:52:46	\N	\N	\N
189	53	92	submitted	draft	in_review	1	\N	2026-08-21 05:53:11	\N	\N	\N
190	53	21	approved	in_review	in_review	1	\N	2026-08-21 05:53:43	\N	\N	\N
191	53	22	approved	in_review	in_review	1	\N	2026-08-21 05:53:50	\N	\N	\N
192	53	22	completed	in_review	approved	1	\N	2026-08-21 05:53:54	\N	\N	\N
193	54	93	submitted	draft	in_review	1	\N	2026-08-21 05:54:15	\N	\N	\N
194	54	21	approved	in_review	in_review	1	\N	2026-08-21 05:54:47	\N	\N	\N
195	54	22	approved	in_review	in_review	1	\N	2026-08-21 05:54:54	\N	\N	\N
196	54	22	completed	in_review	approved	1	\N	2026-08-21 05:54:58	\N	\N	\N
197	55	94	submitted	draft	in_review	1	\N	2026-08-21 05:55:23	\N	\N	\N
198	56	95	submitted	draft	in_review	1	\N	2026-08-21 05:56:06	\N	\N	\N
199	56	21	approved	in_review	in_review	1	\N	2026-08-21 05:56:37	\N	\N	\N
200	56	22	approved	in_review	in_review	1	\N	2026-08-21 05:56:44	\N	\N	\N
201	56	22	completed	in_review	approved	1	\N	2026-08-21 05:56:49	\N	\N	\N
202	57	96	submitted	draft	in_review	1	\N	2026-08-21 05:57:09	\N	\N	\N
203	57	21	approved	in_review	in_review	1	\N	2026-08-21 05:57:41	\N	\N	\N
204	57	22	approved	in_review	in_review	1	\N	2026-08-21 05:57:48	\N	\N	\N
205	57	22	completed	in_review	approved	1	\N	2026-08-21 05:57:53	\N	\N	\N
206	58	97	submitted	draft	in_review	1	\N	2026-08-21 05:58:18	\N	\N	\N
207	58	21	approved	in_review	in_review	1	\N	2026-08-21 05:58:49	\N	\N	\N
404	99	137	submitted	draft	in_review	1	\N	2026-09-03 09:04:24	\N	\N	\N
208	58	22	approved	in_review	in_review	1	\N	2026-08-21 05:58:56	\N	\N	\N
209	58	22	completed	in_review	approved	1	\N	2026-08-21 05:59:01	\N	\N	\N
210	59	98	submitted	draft	in_review	1	\N	2026-08-21 05:59:21	\N	\N	\N
211	59	21	approved	in_review	in_review	1	\N	2026-08-21 05:59:53	\N	\N	\N
212	59	22	approved	in_review	in_review	1	\N	2026-08-21 06:00:00	\N	\N	\N
213	59	22	completed	in_review	approved	1	\N	2026-08-21 06:00:05	\N	\N	\N
214	61	93	submitted	draft	in_review	1	\N	2026-08-21 06:00:35	\N	\N	\N
215	61	21	approved	in_review	in_review	1	\N	2026-08-21 06:01:12	\N	\N	\N
216	62	92	submitted	draft	in_review	1	\N	2026-08-21 06:01:27	\N	\N	\N
217	62	51	approved	in_review	in_review	1	\N	2026-08-21 06:01:47	\N	\N	\N
218	62	51	advanced	in_review	in_review	2	\N	2026-08-21 06:01:51	\N	\N	\N
219	62	31	returned	in_review	returned	2	Please itemize the proposed budget in more detail before this can proceed to the Dean.	2026-08-21 06:02:10	["budget","schedule_venue"]	\N	\N
220	63	98	submitted	draft	in_review	1	\N	2026-08-21 06:02:30	\N	\N	\N
221	63	54	approved	in_review	in_review	1	\N	2026-08-21 06:02:53	\N	\N	\N
222	63	54	advanced	in_review	in_review	2	\N	2026-08-21 06:02:57	\N	\N	\N
223	63	26	approved	in_review	in_review	2	\N	2026-08-21 06:03:16	\N	\N	\N
224	63	26	advanced	in_review	in_review	3	\N	2026-08-21 06:03:21	\N	\N	\N
225	63	21	approved	in_review	in_review	3	\N	2026-08-21 06:03:55	\N	\N	\N
226	63	22	approved	in_review	in_review	3	\N	2026-08-21 06:04:03	\N	\N	\N
227	63	22	advanced	in_review	in_review	4	\N	2026-08-21 06:04:08	\N	\N	\N
228	63	23	approved	in_review	in_review	4	\N	2026-08-21 06:04:25	\N	\N	\N
229	63	23	advanced	in_review	in_review	5	\N	2026-08-21 06:04:30	\N	\N	\N
230	63	24	approved	in_review	in_review	5	\N	2026-08-21 06:04:47	\N	\N	\N
231	63	24	advanced	in_review	in_review	6	\N	2026-08-21 06:04:52	\N	\N	\N
232	63	25	approved	in_review	in_review	6	\N	2026-08-21 06:05:09	\N	\N	\N
233	63	25	completed	in_review	approved	6	\N	2026-08-21 06:05:14	\N	\N	\N
234	64	98	submitted	draft	in_review	1	\N	2026-08-21 06:05:36	\N	\N	\N
235	64	22	rejected	in_review	rejected	1	Duplicates an already-approved event on the calendar this term. Please coordinate timing with SDAO before resubmitting.	2026-08-21 06:06:13	\N	\N	\N
236	65	91	submitted	draft	in_review	1	\N	2026-08-21 06:06:32	\N	\N	\N
237	65	48	approved	in_review	in_review	1	\N	2026-08-21 06:06:55	\N	\N	\N
238	65	48	advanced	in_review	in_review	2	\N	2026-08-21 06:06:59	\N	\N	\N
239	65	28	approved	in_review	in_review	2	\N	2026-08-21 06:07:18	\N	\N	\N
240	65	28	advanced	in_review	in_review	3	\N	2026-08-21 06:07:23	\N	\N	\N
241	65	27	approved	in_review	in_review	3	\N	2026-08-21 06:07:42	\N	\N	\N
242	65	27	advanced	in_review	in_review	4	\N	2026-08-21 06:07:46	\N	\N	\N
243	65	21	approved	in_review	in_review	4	\N	2026-08-21 06:08:20	\N	\N	\N
244	65	22	approved	in_review	in_review	4	\N	2026-08-21 06:08:28	\N	\N	\N
245	65	22	advanced	in_review	in_review	5	\N	2026-08-21 06:08:33	\N	\N	\N
246	65	23	approved	in_review	in_review	5	\N	2026-08-21 06:08:50	\N	\N	\N
247	65	23	advanced	in_review	in_review	6	\N	2026-08-21 06:08:54	\N	\N	\N
248	65	24	approved	in_review	in_review	6	\N	2026-08-21 06:09:12	\N	\N	\N
249	65	24	advanced	in_review	in_review	7	\N	2026-08-21 06:09:16	\N	\N	\N
250	65	25	approved	in_review	in_review	7	\N	2026-08-21 06:09:34	\N	\N	\N
251	65	25	completed	in_review	approved	7	\N	2026-08-21 06:09:38	\N	\N	\N
252	66	93	submitted	draft	in_review	1	\N	2026-08-21 06:10:06	\N	\N	\N
253	66	21	approved	in_review	in_review	1	\N	2026-08-21 06:10:47	\N	\N	\N
254	66	22	approved	in_review	in_review	1	\N	2026-08-21 06:10:55	\N	\N	\N
255	66	22	advanced	in_review	in_review	2	\N	2026-08-21 06:10:59	\N	\N	\N
256	66	85	approved	in_review	in_review	2	\N	2026-08-21 06:11:19	\N	\N	\N
257	66	85	advanced	in_review	in_review	3	\N	2026-08-21 06:11:23	\N	\N	\N
258	66	30	approved	in_review	in_review	3	\N	2026-08-21 06:11:42	\N	\N	\N
259	66	30	advanced	in_review	in_review	4	\N	2026-08-21 06:11:47	\N	\N	\N
260	66	27	approved	in_review	in_review	4	\N	2026-08-21 06:12:06	\N	\N	\N
261	66	27	advanced	in_review	in_review	5	\N	2026-08-21 06:12:10	\N	\N	\N
262	66	23	approved	in_review	in_review	5	\N	2026-08-21 06:12:28	\N	\N	\N
263	66	23	advanced	in_review	in_review	6	\N	2026-08-21 06:12:32	\N	\N	\N
264	66	24	approved	in_review	in_review	6	\N	2026-08-21 06:12:50	\N	\N	\N
265	66	24	advanced	in_review	in_review	7	\N	2026-08-21 06:12:54	\N	\N	\N
266	66	25	approved	in_review	in_review	7	\N	2026-08-21 06:13:12	\N	\N	\N
267	66	25	completed	in_review	approved	7	\N	2026-08-21 06:13:17	\N	\N	\N
268	67	98	submitted	draft	in_review	1	\N	2026-08-21 06:13:48	\N	\N	\N
269	67	21	approved	in_review	in_review	1	\N	2026-08-21 06:14:20	\N	\N	\N
270	67	22	approved	in_review	in_review	1	\N	2026-08-21 06:14:27	\N	\N	\N
271	67	22	completed	in_review	approved	1	\N	2026-08-21 06:14:32	\N	\N	\N
272	68	91	submitted	draft	in_review	1	\N	2026-08-21 06:14:56	\N	\N	\N
273	68	21	approved	in_review	in_review	1	\N	2026-08-21 06:15:26	\N	\N	\N
274	69	93	submitted	draft	in_review	1	\N	2026-08-21 06:15:44	\N	\N	\N
275	69	22	rejected	in_review	rejected	1	Attendance sheet does not match the reported participant count. Please refile with corrected figures.	2026-08-21 06:16:13	\N	\N	\N
276	70	96	submitted	draft	in_review	1	\N	2026-08-23 12:53:43	\N	\N	\N
277	70	21	approved	in_review	in_review	1	\N	2026-08-23 12:59:22	\N	\N	\N
278	71	121	submitted	draft	in_review	1	\N	2026-08-24 05:50:30	\N	\N	\N
279	71	22	returned	in_review	returned	1	Different adviser ang piliin.	2026-08-24 05:56:04	\N	\N	\N
280	71	121	resubmitted	returned	in_review	1	\N	2026-08-24 05:57:03	\N	\N	\N
281	71	22	returned	in_review	returned	1	Palit adviser	2026-08-24 05:59:04	\N	\N	\N
282	71	121	resubmitted	returned	in_review	1	\N	2026-08-24 06:03:39	\N	\N	\N
283	71	22	returned	in_review	returned	1	Bagong adviser	2026-08-24 06:11:44	\N	\N	\N
284	71	121	resubmitted	returned	in_review	1	\N	2026-08-24 06:12:28	\N	\N	\N
285	71	22	returned	in_review	returned	1	In the By-Laws revise the attached file.	2026-08-24 06:14:10	["by_laws"]	{"by_laws":"Revise your attached file"}	\N
286	71	121	resubmitted	returned	in_review	1	\N	2026-08-24 06:16:18	\N	\N	\N
287	71	22	approved	in_review	in_review	1	\N	2026-08-24 06:16:48	\N	\N	\N
288	72	97	submitted	draft	in_review	1	\N	2026-08-24 06:29:56	\N	\N	\N
289	71	41	approved	in_review	in_review	1	\N	2026-08-24 06:33:11	\N	\N	\N
290	71	41	completed	in_review	approved	1	\N	2026-08-24 06:33:12	\N	\N	\N
291	72	41	approved	in_review	in_review	1	\N	2026-08-24 06:35:37	\N	\N	\N
292	72	22	approved	in_review	in_review	1	\N	2026-08-24 06:38:06	\N	\N	\N
293	72	22	completed	in_review	approved	1	\N	2026-08-24 06:38:07	\N	\N	\N
294	73	\N	submitted	draft	in_review	1	\N	2026-08-24 08:49:04	\N	\N	\N
295	73	41	rejected	in_review	rejected	1	wala na acc	2026-08-24 08:59:04	\N	\N	\N
297	74	41	returned	in_review	returned	1	Purpose ng org	2026-08-24 09:15:57	["organization_details"]	{"organization_details":"padagdagan po ang purpose ng org pls"}	\N
299	74	41	approved	in_review	in_review	1	\N	2026-08-24 09:20:19	\N	\N	\N
300	74	42	returned	in_review	returned	1	attached files	2026-08-24 09:25:56	["proposed_projects_budget"]	{"proposed_projects_budget":"Palte are"}	\N
302	74	42	approved	in_review	in_review	1	\N	2026-08-24 09:30:55	\N	\N	\N
303	75	121	submitted	draft	in_review	1	\N	2026-08-24 12:25:53	\N	\N	\N
304	75	41	approved	in_review	in_review	1	\N	2026-08-24 12:30:54	\N	\N	\N
305	75	42	approved	in_review	in_review	1	\N	2026-08-24 12:36:39	\N	\N	\N
306	75	42	completed	in_review	approved	1	\N	2026-08-24 12:36:42	\N	\N	\N
307	76	121	submitted	draft	in_review	1	\N	2026-08-24 12:42:40	\N	\N	\N
308	76	42	returned	in_review	returned	1	The SDG	2026-08-24 12:48:20	\N	\N	\N
309	76	121	resubmitted	returned	in_review	1	\N	2026-08-24 12:50:33	\N	\N	\N
310	78	121	submitted	draft	in_review	1	\N	2026-08-24 13:06:36	\N	\N	\N
311	79	121	submitted	draft	in_review	1	\N	2026-08-24 13:55:08	\N	\N	\N
312	79	41	approved	in_review	in_review	1	\N	2026-08-24 13:57:59	\N	\N	\N
313	80	96	submitted	draft	in_review	1	\N	2026-08-24 16:22:44	\N	\N	\N
314	80	40	approved	in_review	in_review	1	\N	2026-08-24 16:25:33	\N	\N	\N
315	80	40	advanced	in_review	in_review	2	\N	2026-08-24 16:25:34	\N	\N	\N
316	80	38	returned	in_review	returned	2	The submitted objectives were too general and did not clearly connect to the intended outcomes of the activity. Please revise them to highlight specific skills or benefits for participants (e.g., leadership development, career awareness). The revised objectives should be measurable and aligned with the overall purpose of the event.	2026-08-24 16:34:45	["objectives"]	{"objectives":"Revise"}	\N
317	80	96	resubmitted	returned	in_review	2	\N	2026-08-24 16:37:11	\N	\N	{"objectives":{"label":"Objectives","status":"changed","fields":[{"key":"objectives","label":"Objectives","old":"To guide accountancy students in understanding career paths in auditing, taxation, and corporate finance.","new":"In addition to the original goals, the activity now aims to broaden student perspectives by exposing them to diverse career paths in accountancy, strengthen their confidence in pursuing professional examinations, and foster meaningful connections with industry practitioners. These new objectives emphasize practical readiness, career awareness, and networking opportunities, ensuring that the event delivers both academic and professional value to participants.","changed":true}]}}
318	80	38	approved	in_review	in_review	2	\N	2026-08-24 16:38:00	\N	\N	\N
319	80	38	advanced	in_review	in_review	3	\N	2026-08-24 16:38:01	\N	\N	\N
320	80	36	approved	in_review	in_review	3	\N	2026-08-24 16:41:23	\N	\N	\N
321	80	36	advanced	in_review	in_review	4	\N	2026-08-24 16:41:24	\N	\N	\N
322	80	41	approved	in_review	in_review	4	\N	2026-08-24 16:45:20	\N	\N	\N
323	80	42	approved	in_review	in_review	4	\N	2026-08-24 16:47:51	\N	\N	\N
324	80	42	advanced	in_review	in_review	5	\N	2026-08-24 16:47:52	\N	\N	\N
325	80	23	approved	in_review	in_review	5	\N	2026-08-24 16:51:15	\N	\N	\N
326	80	23	advanced	in_review	in_review	6	\N	2026-08-24 16:51:16	\N	\N	\N
327	80	24	approved	in_review	in_review	6	\N	2026-08-24 16:53:00	\N	\N	\N
328	80	24	advanced	in_review	in_review	7	\N	2026-08-24 16:53:02	\N	\N	\N
329	80	25	approved	in_review	in_review	7	\N	2026-08-24 16:54:34	\N	\N	\N
330	80	25	completed	in_review	approved	7	\N	2026-08-24 16:54:35	\N	\N	\N
331	81	96	submitted	draft	in_review	1	\N	2026-08-24 17:06:10	\N	\N	\N
332	81	21	approved	in_review	in_review	1	\N	2026-08-24 17:07:04	\N	\N	\N
333	81	22	approved	in_review	in_review	1	\N	2026-08-24 17:08:36	\N	\N	\N
334	81	22	completed	in_review	approved	1	\N	2026-08-24 17:08:37	\N	\N	\N
335	74	\N	withdrawn	in_review	rejected	1	Withdrawn automatically: the submitting account was deleted.	2026-08-29 14:03:27	\N	\N	\N
296	74	\N	submitted	draft	in_review	1	\N	2026-08-24 09:12:12	\N	\N	\N
359	87	41	returned	in_review	returned	1	Sa lahat ba to boi	2026-08-30 08:43:38	["letter_of_intent","by_laws","dean_endorsement_letter"]	{"letter_of_intent":"ibahin mo to 1","by_laws":"ibahin mo to 2","dean_endorsement_letter":"ibahin mo to 3"}	\N
360	87	137	resubmitted	returned	in_review	1	\N	2026-08-30 08:44:27	\N	\N	{"letter_of_intent":{"label":"Letter of Intent","status":"replaced","fields":[]},"by_laws":{"label":"By-Laws","status":"replaced","fields":[]},"dean_endorsement_letter":{"label":"Letter from College Dean endorsing the Faculty Adviser","status":"replaced","fields":[]}}
362	87	42	returned	in_review	returned	1	need pala to	2026-08-30 08:46:16	["organization_details"]	{"organization_details":"palte mo are"}	\N
402	95	42	completed	in_review	approved	1	\N	2026-09-03 08:59:03	\N	\N	\N
403	98	139	submitted	draft	in_review	1	\N	2026-09-03 09:01:33	\N	\N	\N
298	74	\N	resubmitted	returned	in_review	1	\N	2026-08-24 09:17:36	\N	\N	{"organization_details":{"label":"Organization Details","status":"changed","fields":[{"key":"organization_type","label":"Organization Type","old":"Extra Curricular-Interest Clubs","new":"Extra Curricular-Interest Clubs","changed":false},{"key":"date_organized","label":"Date Organized","old":"Aug 24, 2026","new":"Aug 24, 2026","changed":false},{"key":"purpose_of_organization","label":"Purpose of Organization","old":"The purpose of the organization is to bring together VALORANT players who share a passion for competitive gaming, teamwork, and personal improvement. It aims to provide a positive community where members can develop their skills, improve communication and strategy, participate in tournaments, and build good sportsmanship. The organization also seeks to promote responsible gaming, friendship, and collaboration among its members.","new":"The purpose of the organization is to bring together VALORANT players who share a passion for competitive gaming, teamwork, and personal improvement. It aims to provide a positive community where members can develop their skills, improve communication and strategy, participate in tournaments, and build good sportsmanship. The organization also seeks to promote responsible gaming, friendship, and collaboration among its members.\\n\\nIn addition, the organization aims to create opportunities for members to gain experience in leadership, event management, and team coordination through organized matches, training sessions, and tournaments. It encourages members to support one another, learn from both victories and losses, and maintain respect toward teammates and opponents. Through these activities, the organization hopes to build a strong and active gaming community where members can enjoy VALORANT while developing useful skills that can also be applied outside of gaming.","changed":true}]}}
301	74	\N	resubmitted	returned	in_review	1	\N	2026-08-24 09:27:55	\N	\N	\N
337	82	42	approved	in_review	in_review	1	\N	2026-08-29 14:18:35	\N	\N	\N
338	82	41	approved	in_review	in_review	1	\N	2026-08-29 14:20:16	\N	\N	\N
339	82	41	completed	in_review	approved	1	\N	2026-08-29 14:20:17	\N	\N	\N
340	83	131	submitted	draft	in_review	1	\N	2026-08-30 02:54:06	\N	\N	\N
341	83	41	rejected	in_review	rejected	1	gawa ka bago	2026-08-30 03:45:06	\N	\N	\N
342	84	131	submitted	draft	in_review	1	\N	2026-08-30 03:46:44	\N	\N	\N
343	84	41	returned	in_review	returned	1	PURPOSE OF ORG	2026-08-30 03:47:36	["organization_details","general"]	{"organization_details":"pahabae","general":"palte"}	\N
344	84	131	resubmitted	returned	in_review	1	\N	2026-08-30 03:48:16	\N	\N	{"organization_details":{"label":"Organization Details","status":"changed","fields":[{"key":"organization_type","label":"Organization Type","old":"Co-Curricular","new":"Co-Curricular","changed":false},{"key":"date_organized","label":"Date Organized","old":"Aug 30, 2026","new":"Aug 30, 2026","changed":false},{"key":"purpose_of_organization","label":"Purpose of Organization","old":"iwahjkawshjdkawd","new":"MLBB Org para sa mga emel playurs","changed":true}]}}
345	84	41	returned	in_review	returned	1	palte	2026-08-30 03:49:43	["contact_information","organization_details","application_form","officers_list","proposed_projects_budget"]	{"contact_information":"engk","organization_details":"isa pa","application_form":"pwede na sna","officers_list":"gege","proposed_projects_budget":null}	\N
346	84	131	resubmitted	returned	in_review	1	\N	2026-08-30 03:51:12	\N	\N	{"contact_information":{"label":"Contact Information","status":"changed","fields":[{"key":"contact_person","label":"Contact Person","old":"Jaypee Dela Cruz","new":"Dad Jaypee Dela Cruz","changed":true},{"key":"contact_no","label":"Contact Number","old":"09121230212","new":"09121230213","changed":true},{"key":"email_address","label":"Email Address","old":"jaypee@gmail.com","new":"daddyjaypee@gmail.com","changed":true}]},"organization_details":{"label":"Organization Details","status":"changed","fields":[{"key":"organization_type","label":"Organization Type","old":"Co-Curricular","new":"Co-Curricular","changed":false},{"key":"date_organized","label":"Date Organized","old":"Aug 30, 2026","new":"Aug 30, 2026","changed":false},{"key":"purpose_of_organization","label":"Purpose of Organization","old":"MLBB Org para sa mga emel playurs","new":"MLBB Org para sa mga emel players","changed":true}]},"application_form":{"label":"Application Form","status":"replaced","fields":[]},"officers_list":{"label":"Updated List of Officers\\/Founders","status":"replaced","fields":[]},"proposed_projects_budget":{"label":"List of Proposed Projects with Budget","status":"replaced","fields":[]}}
347	84	42	approved	in_review	in_review	1	\N	2026-08-30 03:52:15	\N	\N	\N
348	84	41	approved	in_review	in_review	1	\N	2026-08-30 03:56:43	\N	\N	\N
349	84	41	completed	in_review	approved	1	\N	2026-08-30 03:56:43	\N	\N	\N
350	85	133	submitted	draft	in_review	1	\N	2026-08-30 04:13:48	\N	\N	\N
351	86	135	submitted	draft	in_review	1	\N	2026-08-30 04:16:09	\N	\N	\N
352	85	41	approved	in_review	in_review	1	\N	2026-08-30 04:16:36	\N	\N	\N
353	85	42	approved	in_review	in_review	1	\N	2026-08-30 04:17:04	\N	\N	\N
354	85	42	completed	in_review	approved	1	\N	2026-08-30 04:17:04	\N	\N	\N
355	86	42	rejected	in_review	rejected	1	gawa ka gabago	2026-08-30 04:19:51	\N	\N	\N
356	87	137	submitted	draft	in_review	1	\N	2026-08-30 08:37:39	\N	\N	\N
357	87	41	returned	in_review	returned	1	balik mo wait	2026-08-30 08:39:49	["contact_information","organization_details","application_form","officers_list","proposed_projects_budget"]	{"contact_information":"contact info","organization_details":"ibahin mo to","application_form":"ibahin mo to","officers_list":"ibahin mo to","proposed_projects_budget":"ibahin mot o"}	\N
358	87	137	resubmitted	returned	in_review	1	\N	2026-08-30 08:42:34	\N	\N	{"contact_information":{"label":"Contact Information","status":"changed","fields":[{"key":"contact_person","label":"Contact Person","old":"Manjean Faldas","new":"Manjean Faldas","changed":false},{"key":"contact_no","label":"Contact Number","old":"09827129898","new":"09827129812","changed":true},{"key":"email_address","label":"Email Address","old":"jaypee@gmail.com","new":"jaypee@gmail.com","changed":false}]},"organization_details":{"label":"Organization Details","status":"changed","fields":[{"key":"organization_type","label":"Organization Type","old":"Extra Curricular-Interest Clubs","new":"Extra Curricular-Interest Clubs","changed":false},{"key":"date_organized","label":"Date Organized","old":"Aug 30, 2026","new":"Aug 30, 2026","changed":false},{"key":"purpose_of_organization","label":"Purpose of Organization","old":"last testing","new":"last testing mic check 123","changed":true}]},"application_form":{"label":"Application Form","status":"replaced","fields":[]},"officers_list":{"label":"Updated List of Officers\\/Founders","status":"replaced","fields":[]},"proposed_projects_budget":{"label":"List of Proposed Projects with Budget","status":"replaced","fields":[]}}
361	87	41	approved	in_review	in_review	1	\N	2026-08-30 08:44:57	\N	\N	\N
363	87	137	resubmitted	returned	in_review	1	\N	2026-08-30 08:46:43	\N	\N	{"organization_details":{"label":"Organization Details","status":"changed","fields":[{"key":"organization_type","label":"Organization Type","old":"Extra Curricular-Interest Clubs","new":"Extra Curricular-Interest Clubs","changed":false},{"key":"date_organized","label":"Date Organized","old":"Aug 30, 2026","new":"Aug 30, 2026","changed":false},{"key":"purpose_of_organization","label":"Purpose of Organization","old":"last testing mic check 123","new":"purpose of organization","changed":true}]}}
364	87	42	approved	in_review	in_review	1	\N	2026-08-30 08:47:51	\N	\N	\N
365	87	41	approved	in_review	in_review	1	\N	2026-08-30 14:35:14	\N	\N	\N
366	87	41	completed	in_review	approved	1	\N	2026-08-30 14:35:15	\N	\N	\N
367	88	138	submitted	draft	in_review	1	\N	2026-08-30 14:41:20	\N	\N	\N
368	88	41	approved	in_review	in_review	1	\N	2026-08-30 14:41:29	\N	\N	\N
369	88	42	returned	in_review	returned	1	a	2026-08-30 14:42:12	["contact_information","organization_details","adviser_selection","letter_of_intent","application_form","by_laws"]	{"contact_information":"b","organization_details":"c","adviser_selection":"d","letter_of_intent":"e","application_form":"f","by_laws":"g"}	\N
370	88	138	resubmitted	returned	in_review	1	\N	2026-08-30 14:42:26	\N	\N	{"contact_information":{"label":"Contact Information","status":"changed","fields":[{"key":"contact_person","label":"Contact Person","old":"Marvin Atanacio","new":"b","changed":true},{"key":"contact_no","label":"Contact Number","old":"09121230212","new":"09121230212","changed":false},{"key":"email_address","label":"Email Address","old":"marvin.atanacio@nu-lipa.edu.ph","new":"marvin.atanacio@nu-lipa.edu.ph","changed":false}]},"organization_details":{"label":"Organization Details","status":"changed","fields":[{"key":"organization_type","label":"Organization Type","old":"Extra Curricular-Interest Clubs","new":"Extra Curricular-Interest Clubs","changed":false},{"key":"date_organized","label":"Date Organized","old":"Aug 30, 2026","new":"Aug 30, 2026","changed":false},{"key":"purpose_of_organization","label":"Purpose of Organization","old":"asdasd","new":"a","changed":true}]},"adviser_selection":{"label":"Adviser Selection","status":"changed","fields":[{"key":"adviser_id","label":"Adviser","old":"Blood Test","new":"Blood Test","changed":false}]},"letter_of_intent":{"label":"Letter of Intent","status":"unchanged","fields":[]},"application_form":{"label":"Application Form","status":"unchanged","fields":[]},"by_laws":{"label":"By-Laws","status":"unchanged","fields":[]}}
371	88	42	approved	in_review	in_review	1	\N	2026-08-30 14:42:41	\N	\N	\N
372	88	41	approved	in_review	in_review	1	\N	2026-08-30 14:46:36	\N	\N	\N
373	88	41	completed	in_review	approved	1	\N	2026-08-30 14:46:36	\N	\N	\N
374	89	138	submitted	draft	in_review	1	\N	2026-08-30 14:53:33	\N	\N	\N
375	89	41	approved	in_review	in_review	1	\N	2026-08-30 14:56:04	\N	\N	\N
376	89	42	approved	in_review	in_review	1	\N	2026-08-30 14:56:27	\N	\N	\N
377	89	42	completed	in_review	approved	1	\N	2026-08-30 14:56:27	\N	\N	\N
336	82	\N	submitted	draft	in_review	1	\N	2026-08-29 14:15:49	\N	\N	\N
378	90	139	submitted	draft	in_review	1	\N	2026-09-03 08:06:37	\N	\N	\N
379	90	41	approved	in_review	in_review	1	\N	2026-09-03 08:06:50	\N	\N	\N
380	90	42	approved	in_review	in_review	1	\N	2026-09-03 08:07:09	\N	\N	\N
381	90	42	completed	in_review	approved	1	\N	2026-09-03 08:07:09	\N	\N	\N
382	91	139	submitted	draft	in_review	1	\N	2026-09-03 08:08:03	\N	\N	\N
383	91	42	rejected	in_review	rejected	1	asdasd	2026-09-03 08:08:51	\N	\N	\N
384	92	139	submitted	draft	in_review	1	\N	2026-09-03 08:10:23	\N	\N	\N
385	92	42	approved	in_review	in_review	1	\N	2026-09-03 08:10:55	\N	\N	\N
386	92	41	approved	in_review	in_review	1	\N	2026-09-03 08:11:10	\N	\N	\N
387	92	41	completed	in_review	approved	1	\N	2026-09-03 08:11:10	\N	\N	\N
388	93	139	submitted	draft	in_review	1	\N	2026-09-03 08:20:31	\N	\N	\N
389	93	41	rejected	in_review	rejected	1	olit	2026-09-03 08:21:17	\N	\N	\N
390	94	139	submitted	draft	in_review	1	\N	2026-09-03 08:22:06	\N	\N	\N
391	94	41	rejected	in_review	rejected	1	haha	2026-09-03 08:23:29	\N	\N	\N
392	95	139	submitted	draft	in_review	1	\N	2026-09-03 08:27:22	\N	\N	\N
393	96	137	submitted	draft	in_review	1	\N	2026-09-03 08:29:50	\N	\N	\N
394	96	41	approved	in_review	in_review	1	\N	2026-09-03 08:30:50	\N	\N	\N
395	96	42	approved	in_review	in_review	1	\N	2026-09-03 08:31:42	\N	\N	\N
396	96	42	completed	in_review	approved	1	\N	2026-09-03 08:31:42	\N	\N	\N
397	97	131	submitted	draft	in_review	1	\N	2026-09-03 08:36:44	\N	\N	\N
398	95	41	returned	in_review	returned	1	palte ang name ng 1 at 3 bonso	2026-09-03 08:38:56	["activity_0","activity_2"]	\N	\N
399	95	139	resubmitted	returned	in_review	1	\N	2026-09-03 08:52:01	\N	\N	{"activity_0":{"label":"Activity 1","status":"changed","fields":[{"key":"name","label":"Activity Name","old":"act 1 dayet","new":"act 1 dayet revised","changed":true},{"key":"venue","label":"Venue","old":"gytm","new":"gytm revised","changed":true},{"key":"activity_date","label":"Date","old":"Sep 10, 2026","new":"Sep 10, 2026","changed":false},{"key":"start_time","label":"Start Time","old":"18:23","new":"18:23","changed":false},{"key":"end_time","label":"End Time","old":"20:23","new":"20:23","changed":false},{"key":"sdg","label":"SDG","old":"Zero Hunger","new":"Zero Hunger","changed":false},{"key":"participant_program_assigned","label":"Participants \\/ Program","old":"bscs","new":"bscs revised","changed":true},{"key":"budget","label":"Budget","old":"\\u20b11,000.00","new":"\\u20b11,000.00","changed":false},{"key":"description","label":"Description","old":"haha","new":"haha revised","changed":true}]},"activity_2":{"label":"Activity 3","status":"changed","fields":[{"key":"name","label":"Activity Name","old":"act 2 dayet","new":"act 2 dayet revised","changed":true},{"key":"venue","label":"Venue","old":"gymn","new":"gymn revised","changed":true},{"key":"activity_date","label":"Date","old":"Sep 17, 2026","new":"Sep 17, 2026","changed":false},{"key":"start_time","label":"Start Time","old":"18:26","new":"18:26","changed":false},{"key":"end_time","label":"End Time","old":"20:26","new":"20:26","changed":false},{"key":"sdg","label":"SDG","old":"Zero Hunger","new":"Zero Hunger","changed":false},{"key":"participant_program_assigned","label":"Participants \\/ Program","old":"bsb","new":"bsb revised","changed":true},{"key":"budget","label":"Budget","old":"\\u20b19,090.00","new":"\\u20b19,090.00","changed":false},{"key":"description","label":"Description","old":"hjhj","new":"hjhj revised","changed":true}]}}
400	95	41	approved	in_review	in_review	1	\N	2026-09-03 08:58:39	\N	\N	\N
401	95	42	approved	in_review	in_review	1	\N	2026-09-03 08:59:03	\N	\N	\N
405	98	42	approved	in_review	in_review	1	\N	2026-09-03 09:04:32	\N	\N	\N
406	98	41	approved	in_review	in_review	1	\N	2026-09-03 09:05:27	\N	\N	\N
407	98	41	completed	in_review	approved	1	\N	2026-09-03 09:05:27	\N	\N	\N
408	100	137	submitted	draft	in_review	1	\N	2026-09-03 09:07:25	\N	\N	\N
409	101	139	submitted	draft	in_review	1	\N	2026-09-03 09:08:31	\N	\N	\N
410	99	41	rejected	in_review	rejected	1	asdas	2026-09-03 09:08:36	\N	\N	\N
411	103	139	submitted	draft	in_review	1	\N	2026-09-03 09:56:07	\N	\N	\N
412	103	140	approved	in_review	in_review	1	\N	2026-09-03 10:00:20	\N	\N	\N
413	103	140	advanced	in_review	in_review	2	\N	2026-09-03 10:00:20	\N	\N	\N
414	103	29	approved	in_review	in_review	2	\N	2026-09-03 10:03:26	\N	\N	\N
415	103	29	advanced	in_review	in_review	3	\N	2026-09-03 10:03:26	\N	\N	\N
416	103	27	approved	in_review	in_review	3	\N	2026-09-03 10:05:48	\N	\N	\N
417	103	27	advanced	in_review	in_review	4	\N	2026-09-03 10:05:48	\N	\N	\N
418	103	41	approved	in_review	in_review	4	\N	2026-09-03 10:08:30	\N	\N	\N
419	103	42	approved	in_review	in_review	4	\N	2026-09-03 10:09:34	\N	\N	\N
420	103	42	advanced	in_review	in_review	5	\N	2026-09-03 10:09:34	\N	\N	\N
421	102	139	submitted	draft	in_review	1	\N	2026-09-05 11:01:46	\N	\N	\N
422	102	140	approved	in_review	in_review	1	\N	2026-09-05 11:03:35	\N	\N	\N
423	102	140	advanced	in_review	in_review	2	\N	2026-09-05 11:03:35	\N	\N	\N
424	102	29	approved	in_review	in_review	2	\N	2026-09-05 11:06:17	\N	\N	\N
425	102	29	advanced	in_review	in_review	3	\N	2026-09-05 11:06:17	\N	\N	\N
426	102	27	approved	in_review	in_review	3	\N	2026-09-05 11:06:41	\N	\N	\N
427	102	27	advanced	in_review	in_review	4	\N	2026-09-05 11:06:41	\N	\N	\N
428	102	41	approved	in_review	in_review	4	\N	2026-09-05 11:07:12	\N	\N	\N
429	102	42	approved	in_review	in_review	4	\N	2026-09-05 11:07:43	\N	\N	\N
430	102	42	advanced	in_review	in_review	5	\N	2026-09-05 11:07:43	\N	\N	\N
431	102	23	approved	in_review	in_review	5	\N	2026-09-05 13:20:46	\N	\N	\N
432	102	23	advanced	in_review	in_review	6	\N	2026-09-05 13:20:46	\N	\N	\N
433	102	24	approved	in_review	in_review	6	\N	2026-09-05 13:21:15	\N	\N	\N
434	102	24	advanced	in_review	in_review	7	\N	2026-09-05 13:21:15	\N	\N	\N
435	102	25	approved	in_review	in_review	7	\N	2026-09-05 13:21:59	\N	\N	\N
436	102	25	completed	in_review	approved	7	\N	2026-09-05 13:21:59	\N	\N	\N
437	104	141	submitted	draft	in_review	1	\N	2026-09-05 13:39:55	\N	\N	\N
438	104	41	approved	in_review	in_review	1	\N	2026-09-05 13:40:07	\N	\N	\N
439	104	42	approved	in_review	in_review	1	\N	2026-09-05 13:41:49	\N	\N	\N
440	104	42	completed	in_review	approved	1	\N	2026-09-05 13:41:49	\N	\N	\N
\.


--
-- Data for Name: documents; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.documents (id, form_type, variant, title, status, current_step_position, organization_id, workflow_template_id, submitted_by, created_at, updated_at) FROM stdin;
70	activity_calendar	\N	Activity Calendar — JPIA (1st Term 2026-2027)	in_review	1	21	3	96	2026-08-23 12:53:40	2026-08-23 12:53:42
35	organization_registration	\N	Organization Registration — CODECS (2026-2027)	approved	\N	16	1	91	2026-08-21 05:32:35	2026-08-21 05:33:37
36	organization_registration	\N	Organization Registration — UAPSA (2026-2027)	approved	\N	17	1	92	2026-08-21 05:34:01	2026-08-21 05:35:03
73	organization_registration	\N	Organization Registration — Valorant Club (2026-2027)	rejected	\N	29	1	\N	2026-08-24 08:48:56	2026-08-24 08:59:04
37	organization_registration	\N	Organization Registration — PICE (2026-2027)	approved	\N	18	1	93	2026-08-21 05:35:27	2026-08-21 05:36:29
38	organization_registration	\N	Organization Registration — Psychology Org (2026-2027)	approved	\N	19	1	94	2026-08-21 05:36:53	2026-08-21 05:37:55
76	activity_calendar	\N	Activity Calendar — Cosplayers (1st Term 2026-2027)	in_review	1	28	3	121	2026-08-24 12:42:38	2026-08-24 12:50:32
39	organization_registration	\N	Organization Registration — MTSC (2026-2027)	approved	\N	20	1	95	2026-08-21 05:38:19	2026-08-21 05:39:22
40	organization_registration	\N	Organization Registration — JPIA (2026-2027)	approved	\N	21	1	96	2026-08-21 05:39:46	2026-08-21 05:40:48
79	activity_proposal	regular_off_calendar	Activity Proposal — Student Leadership Workshop (Cosplayers)	in_review	1	28	6	121	2026-08-24 13:52:33	2026-08-24 13:55:08
41	organization_registration	\N	Organization Registration — Red Cross Youth (2026-2027)	approved	\N	22	1	97	2026-08-21 05:41:12	2026-08-21 05:42:15
42	organization_registration	\N	Organization Registration — Venaris Esports (2026-2027)	approved	\N	23	1	98	2026-08-21 05:42:39	2026-08-21 05:43:42
43	organization_registration	\N	Organization Registration — G17 (2026-2027)	draft	\N	24	\N	102	2026-08-21 05:44:21	2026-08-21 05:44:21
44	organization_registration	\N	Organization Registration — NEXUS (2026-2027)	in_review	1	25	1	103	2026-08-21 05:44:26	2026-08-21 05:44:41
45	organization_registration	\N	Organization Registration — COMEX (2026-2027)	returned	1	26	1	104	2026-08-21 05:45:22	2026-08-21 05:46:07
46	organization_registration	\N	Organization Registration — CREA8ives (2026-2027)	rejected	\N	27	1	105	2026-08-21 05:46:19	2026-08-21 05:47:03
47	organization_renewal	\N	Organization Renewal — CODECS (2026-2027)	draft	\N	16	\N	91	2026-08-21 05:47:16	2026-08-21 05:47:16
48	organization_renewal	\N	Organization Renewal — UAPSA (2026-2027)	in_review	1	17	2	92	2026-08-21 05:47:25	2026-08-21 05:47:45
49	organization_renewal	\N	Organization Renewal — PICE (2026-2027)	returned	1	18	2	93	2026-08-21 05:48:24	2026-08-21 05:49:14
50	organization_renewal	\N	Organization Renewal — JPIA (2026-2027)	approved	\N	21	2	96	2026-08-21 05:49:32	2026-08-21 05:50:34
51	organization_renewal	\N	Organization Renewal — Red Cross Youth (2026-2027)	rejected	\N	22	2	97	2026-08-21 05:50:52	2026-08-21 05:51:41
52	activity_calendar	\N	Activity Calendar — CODECS (1st Term 2026-2027)	approved	\N	16	3	91	2026-08-21 05:51:55	2026-08-21 05:52:45
53	activity_calendar	\N	Activity Calendar — UAPSA (1st Term 2026-2027)	approved	\N	17	3	92	2026-08-21 05:53:05	2026-08-21 05:53:53
54	activity_calendar	\N	Activity Calendar — PICE (1st Term 2026-2027)	approved	\N	18	3	93	2026-08-21 05:54:09	2026-08-21 05:54:57
55	activity_calendar	\N	Activity Calendar — Psychology Org (1st Term 2026-2027)	in_review	1	19	3	94	2026-08-21 05:55:17	2026-08-21 05:55:22
56	activity_calendar	\N	Activity Calendar — MTSC (1st Term 2026-2027)	approved	\N	20	3	95	2026-08-21 05:55:59	2026-08-21 05:56:48
57	activity_calendar	\N	Activity Calendar — JPIA (1st Term 2026-2027)	approved	\N	21	3	96	2026-08-21 05:57:03	2026-08-21 05:57:52
71	organization_registration	\N	Organization Registration — Cosplayers (2026-2027)	approved	\N	28	1	121	2026-08-24 05:50:22	2026-08-24 06:33:12
58	activity_calendar	\N	Activity Calendar — Red Cross Youth (1st Term 2026-2027)	approved	\N	22	3	97	2026-08-21 05:58:11	2026-08-21 05:59:00
59	activity_calendar	\N	Activity Calendar — Venaris Esports (1st Term 2026-2027)	approved	\N	23	3	98	2026-08-21 05:59:15	2026-08-21 06:00:04
60	activity_proposal	\N	Activity Proposal — Hackathon Kickoff (CODECS)	draft	\N	16	\N	91	2026-08-21 06:00:18	2026-08-21 06:00:18
61	activity_proposal	regular_off_calendar	Activity Proposal — Civil Engineering Site Visit (PICE)	in_review	1	18	6	93	2026-08-21 06:00:23	2026-08-21 06:00:34
77	activity_proposal	\N	Activity Proposal — Pakain Program (Cosplayers)	draft	\N	28	\N	121	2026-08-24 13:01:05	2026-08-24 13:01:05
62	activity_proposal	regular_on_calendar	Activity Proposal — Architecture Expo (UAPSA)	returned	2	17	5	92	2026-08-21 06:01:18	2026-08-21 06:02:09
74	organization_registration	\N	Organization Registration — Valorant Club (2026-2027)	rejected	\N	30	1	\N	2026-08-24 09:12:04	2026-08-29 14:03:26
63	activity_proposal	shs_on_calendar	Activity Proposal — Valorant Campus Cup (Venaris Esports)	approved	\N	23	7	98	2026-08-21 06:02:20	2026-08-21 06:05:13
80	activity_proposal	regular_on_calendar	Activity Proposal — Accountancy Career Talk (JPIA)	approved	\N	21	5	96	2026-08-24 16:20:18	2026-08-24 16:54:35
64	activity_proposal	shs_off_calendar	Activity Proposal — SHS Talent Night (Venaris Esports)	rejected	\N	23	8	98	2026-08-21 06:05:25	2026-08-21 06:06:12
72	organization_renewal	\N	Organization Renewal — Red Cross Youth (2026-2027)	approved	\N	22	2	97	2026-08-24 06:29:47	2026-08-24 06:38:07
65	activity_proposal	regular_on_calendar	Activity Proposal — Code Review Bootcamp (CODECS)	approved	\N	16	5	91	2026-08-21 06:06:22	2026-08-21 06:09:37
75	activity_calendar	\N	Activity Calendar — Cosplayers (1st Term 2026-2027)	approved	\N	28	3	121	2026-08-24 12:25:51	2026-08-24 12:36:41
78	activity_proposal	regular_on_calendar	Activity Proposal — Pakain Program (Cosplayers)	in_review	1	28	5	121	2026-08-24 13:02:21	2026-08-24 13:06:36
81	after_activity_report	\N	After-Activity Report — Accountancy Career Talk	approved	\N	21	4	96	2026-08-24 17:06:05	2026-08-24 17:08:37
66	activity_proposal	regular_off_calendar	Activity Proposal — Infrastructure Career Fair (PICE)	approved	\N	18	6	93	2026-08-21 06:09:54	2026-08-21 06:13:16
67	after_activity_report	\N	After-Activity Report — Valorant Campus Cup	approved	\N	23	4	98	2026-08-21 06:13:37	2026-08-21 06:14:31
68	after_activity_report	\N	After-Activity Report — Code Review Bootcamp	in_review	1	16	4	91	2026-08-21 06:14:45	2026-08-21 06:14:55
69	after_activity_report	\N	After-Activity Report — Infrastructure Career Fair	rejected	\N	18	4	93	2026-08-21 06:15:33	2026-08-21 06:16:12
104	organization_registration	\N	Organization Registration — Gen Org (2026-2027)	approved	\N	43	1	141	2026-09-05 13:39:54	2026-09-05 13:41:49
83	organization_registration	\N	Organization Registration — Team Liquid PH (2026-2027)	rejected	\N	35	1	131	2026-08-30 02:54:04	2026-08-30 03:45:06
84	organization_registration	\N	Organization Registration — TLPH (2026-2027)	approved	\N	36	1	131	2026-08-30 03:46:42	2026-08-30 03:56:43
85	organization_registration	\N	Organization Registration — Testing Org (2026-2027)	approved	\N	37	1	133	2026-08-30 04:13:47	2026-08-30 04:17:04
86	organization_registration	\N	Organization Registration — Testing Org 2 (2026-2027)	rejected	\N	38	1	135	2026-08-30 04:16:08	2026-08-30 04:19:51
87	organization_registration	\N	Organization Registration — Extra Curricular Testing (2026-2027)	approved	\N	40	1	137	2026-08-30 08:37:37	2026-08-30 14:35:15
88	organization_registration	\N	Organization Registration — Rano El Org (2026-2027)	approved	\N	41	1	138	2026-08-30 14:41:16	2026-08-30 14:46:36
89	organization_renewal	\N	Organization Renewal — Rano El Org (2026-2027)	approved	\N	41	2	138	2026-08-30 14:53:31	2026-08-30 14:56:27
82	organization_registration	\N	Organization Registration — Dota Club (2026-2027)	approved	\N	31	1	\N	2026-08-29 14:15:40	2026-08-29 14:20:17
90	organization_registration	\N	Organization Registration — Dayether's Org (2026-2027)	approved	\N	42	1	139	2026-09-03 08:06:36	2026-09-03 08:07:09
91	organization_renewal	\N	Organization Renewal — Dayether's Org (2026-2027)	rejected	\N	42	2	139	2026-09-03 08:08:01	2026-09-03 08:08:51
92	organization_renewal	\N	Organization Renewal — Dayether's Org (2026-2027)	approved	\N	42	2	139	2026-09-03 08:10:21	2026-09-03 08:11:10
93	activity_calendar	\N	Activity Calendar — Dayether's Org (1st Term 2026-2027)	rejected	\N	42	3	139	2026-09-03 08:20:31	2026-09-03 08:21:17
94	activity_calendar	\N	Activity Calendar — Dayether's Org (1st Term 2026-2027)	rejected	\N	42	3	139	2026-09-03 08:22:06	2026-09-03 08:23:29
96	activity_calendar	\N	Activity Calendar — Extra Curricular Testing (1st Term 2026-2027)	approved	\N	40	3	137	2026-09-03 08:29:50	2026-09-03 08:31:42
97	activity_calendar	\N	Activity Calendar — TLPH (1st Term 2026-2027)	in_review	1	36	3	131	2026-09-03 08:36:44	2026-09-03 08:36:44
95	activity_calendar	\N	Activity Calendar — Dayether's Org (1st Term 2026-2027)	approved	\N	42	3	139	2026-09-03 08:27:22	2026-09-03 08:59:03
98	activity_calendar	\N	Activity Calendar — Dayether's Org (1st Term 2026-2027)	approved	\N	42	3	139	2026-09-03 09:01:33	2026-09-03 09:05:27
100	activity_calendar	\N	Activity Calendar — Extra Curricular Testing (1st Term 2026-2027)	in_review	1	40	3	137	2026-09-03 09:07:25	2026-09-03 09:07:25
101	activity_calendar	\N	Activity Calendar — Dayether's Org (1st Term 2026-2027)	in_review	1	42	3	139	2026-09-03 09:08:31	2026-09-03 09:08:31
99	activity_calendar	\N	Activity Calendar — Extra Curricular Testing (1st Term 2026-2027)	rejected	\N	40	3	137	2026-09-03 09:04:24	2026-09-03 09:08:36
103	activity_proposal	regular_on_calendar	Activity Proposal — Sept 9 Act (Dayether's Org)	in_review	5	42	5	139	2026-09-03 09:29:53	2026-09-03 10:09:34
102	activity_proposal	regular_on_calendar	Activity Proposal — Sept 9 Act (Dayether's Org)	approved	\N	42	5	139	2026-09-03 09:11:41	2026-09-05 13:21:59
\.


--
-- Data for Name: email_verification_codes; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.email_verification_codes (id, email, purpose, code_hash, payload, user_id, attempts, locked_until, expires_at, consumed_at, created_at, updated_at) FROM stdin;
37	virayrl@students.nu-lipa.edu.ph	registration	$2y$12$Zd..IwRWrN7KmtkJ8nwxUOqLqo5pKgKvGUxxqY2DRqnXTf47bljtW	eyJpdiI6IjhLVkgyRE9WQmUrQTRDUytURVdhbVE9PSIsInZhbHVlIjoiWUtQNFRUYjFZR3pYUlVzaXlsS1R0d1VKRmdFVjJ4M29YL3hEMnJBZG1EY3ZQbDZDVVFoNElOeUp3VVkwQ05vQ3ZIeVVBZ0JZYTFqU0F0WUdCN3lyU3RvZDZLUmhMWklHTmJxZzRuUTlyNlBPclVqUEJ1amJQYUtQWERtcHJSWHdYOHlGSHdSSUFxQUZOU1REdlE4QkprWXUxK2tVamcwanRpRHZ0Zk1wZitsYVJMc3QzeWprdWwyUGdWMVFUSWZZK3ZiZkREbDFyZWl3ZGREcW8zd2pVVTRKd1VFZDZoUkhieXlTbFd0VjRBST0iLCJtYWMiOiIwZGY3NDg4YjFiYjJiMDRlZjQ5NzUwOGM1YzI0MjZmMjNkNGJiZjYxNWQ3MGZlZGRlYjM1MDFiZDI1NzdjNjdlIiwidGFnIjoiIn0=	\N	0	\N	2026-08-24 05:09:51	2026-08-24 04:56:18	2026-08-24 04:54:51	2026-08-24 04:56:19
28	virayrl@students.nu-lipa.edu.ph	registration	$2y$12$s.ViULsCAUp0TRyCZznyq.7Ff4qLitNb7CJAnPKa6RTGa7qx6xF8.	eyJpdiI6ImlEQkxidTVMa1kzYm1mUmUrbEltMXc9PSIsInZhbHVlIjoiMTM5VXl4NWxBSTVna2ZESmcxL2pzd2xUR1dqcEE3VnB6VFVEREVYQ1dYTVJKYWtjdG5ocDdGeFMyTzE2cEVGY0o0MVVOeHR4azRDb1FQb054WEJyOTVCSXR5TEZyM2QrMjlKbjJLTVJud0phaGlCS1AzZDMvWFVvQnV3cnVWVE5YWEtoaTc4SjNlRjNITmVsUXNFaE43cU5qandIcEtKU1ZsNWRGaDZTcThyanUvVmFJSWZucnlCTVFuam5uMWVmejc1K3RNRkJxZENCY3A4KzFHRHlqaG9zbkhKbUhBMmV4Rk5XTkJvcG50MD0iLCJtYWMiOiIxNTY4ZjczNzZkODc5OGI2OWEyODI3ZjJiNGFlODFmZTM4YWIxMmMxYzYwZDhlOTdkYjEyMDg0YjgyOTZiODVjIiwidGFnIjoiIn0=	\N	0	\N	2026-08-24 02:10:21	2026-08-24 01:56:30	2026-08-24 01:55:21	2026-08-24 01:56:30
34	alcantaraka@students.nu-lipa.edu.ph	registration	$2y$12$dyNHSN2lk3KDzl8KravCKeZiFvnDDa7CtT2BU/ETowOHDAFsKVrTC	eyJpdiI6IklLK1I1aVNxbGJGRU9lQUdPRG9YT3c9PSIsInZhbHVlIjoiNGVENHFnSVVYNzNOdDJYc1lBWVVpZDJZWnVCR3MzandOMkx2L0Q2UnoydHJiTVVXRE9yN2hkSU1pLytBd0JucC9LeURDWGxUbHE1UjZsbmp2YlErQ1lyRFJ2aTgyb25ONjQ0ZjJXby9ZNmdWNTN1YlQ4OTF0eTdCQTRBTzVSSUJGNUtHeDZuWkd6RnRWcWJhTldHUHU4dStOSXczR1FQTmtyVHY1a2xBWXIxT2U3NUlHaS9qdGY5SmZMMnQva3JhdU1UM01wNW1mMzdpTGcySEdmQXYwNkJobzJQUXNwRFAxTDdHdjVMeUVROD0iLCJtYWMiOiJjMDRkNDJmYzY1N2JjZjdmNTZiZGI5OWU5YzUwMzE1YWQzZjI2MjQwMzExMmNlMTU1MDFlNGE3ZTBlM2IyYmI0IiwidGFnIjoiIn0=	\N	0	\N	2026-08-24 04:26:24	2026-08-24 04:12:48	2026-08-24 04:11:24	2026-08-24 04:12:48
31	alcantaraka@students.nu-lipa.edu.ph	registration	$2y$12$FzkgMWjDjwV2Oy6XX.SuF.TcoTHnyCqs2DwP9rZCXQWjpa2or9iTK	eyJpdiI6IlJpNktIQzhkMnppS2Y4dHZaVDUweFE9PSIsInZhbHVlIjoicW0zN2FuanVqUEI2WkJpZmFTOEVza2x4dDNJVnEyUDd0M0xteE1HRU1IeHNCeXJmNFlmM1ZvUy9iMnV3K1BZWkVBMkJrd2dTVU03dDJkWFFKZUFWcndXZDl2T0dPM1dIMDNBSGdIUHNYZDFnN0FXZ0QxcEwyZWQzV0hORU1qbm01bDFHS3RLMmxsaENiMWdzRSt6Z29jRGRhM0pHV2dBUUlOTnJHOW80QW1MM0RzYTZDcWNZNHJDYnBTOTlCbmszVUNGVlF1Q1Z2MHZvVXp3eWtOQnhHbEcvRkt4RnNaZEZ2WTVQczNhTzhjZz0iLCJtYWMiOiJkOWU1NTcxMjJmZGZiMzQyZDBhNjNiNWM3Y2FkOGYyY2I5ZjE1ZjJhOGNiYjYwZWM2MDJmYmQ0MjVjNzRkZDIwIiwidGFnIjoiIn0=	\N	0	\N	2026-08-24 03:52:26	2026-08-24 03:44:49	2026-08-24 03:37:26	2026-08-24 03:44:50
36	alcantaraka@students.nu-lipa.edu.ph	registration	$2y$12$3iYCCT/h3dXprfYEoOzzJ.HMFJ1iQtnYzsrXKWPlfCI5x5.Xmt0sa	eyJpdiI6Ikt1eU5TK2MzUFhJYlRGZTRyRm5oM3c9PSIsInZhbHVlIjoiVkM5aGpSaGZiaE1ZNnFCRHdrNmFySHAvQ1U5ZlM3UkRJMXNRL3B2S1R3TjMvbldRcmZKSE5QVlltK2R3U21TdzFHWXdUSzdYT2JQcmNhMTErc0cxT045dUZ5SnNMdUtKRGs1M1FPb0tIUGZIenBUajZVenBQYWFUR3k5Wk05c2pUNWpqRHJEZ0lWbFl3UkZLZEdSVHkxR0VFVzdjNDJkWnI2NnhOMGdjY0xXS1Y5N2VqNHJPWFpEYmZmWEt3TGVRd3BxcHhvQkVrOW1rQ1gwcUM4MFlZZ2QrQnRmM0srS25xbUV4bGZNTVl5bz0iLCJtYWMiOiJlODY3NGRjMTg5ZWI5ZWFlZGM5YTg5MWJjYzhkYjg4YWFhM2ZmNjBiYTlhZGNhNzA4ZGJhNzJhZWVhNDg3ZTY1IiwidGFnIjoiIn0=	\N	0	\N	2026-08-24 04:45:10	2026-08-24 04:31:55	2026-08-24 04:30:10	2026-08-24 04:31:56
30	virayrl@students.nu-lipa.edu.ph	registration	$2y$12$fTOCLl08Pxm86Xs84BRNcu7lYX325u5w0/W84O0o0h.pIrhTYDI5i	eyJpdiI6IkN6MUhua0sxejRGL29FMytpVnFlcHc9PSIsInZhbHVlIjoiaHNwVWNSOUxxT0pFc1Z4OEcxQUl2QzlYbXdwdCtTOUtmdnE4TlFrREtkV0wxallqNDhYYmdrWFZMcFY1T1hiNWZWTVVwN3RPSkMzMWt0VTBRdThyYmJsMGhoMS9HMGNyOUxvaEZST2hMNVlxV2M0MS96Wk1ybFgrdzRITXc0NnBiRjl1TEhjcFdBNXpKUUFXdWlkcDdjYmdVeTl3WWIzNU85L3crUmdHZ2lzak1ZTVU0dEZ2Yi9sME9CNlZLdHUrY21ka3NjamIvR2wzTUhJL3FNNVNDaFdIMjI2MlpqbW9Ca21uOG1rVXRUYz0iLCJtYWMiOiIyMWU1MGM5ZTdmN2FlNDM3MGE1ZjdiYTRjZWExMDRmNzJiM2UwMjc0MGJlNDExYWM0YzU5NDg4MzRmNTUxMTNiIiwidGFnIjoiIn0=	\N	0	\N	2026-08-24 02:17:45	2026-08-24 02:04:40	2026-08-24 02:02:45	2026-08-24 02:04:41
38	alcantaraka@students.nu-lipa.edu.ph	registration	$2y$12$RMMpAPim8UviewZxS.JC9e5sOcdIPD9C4o4pVnbHGpQZB2/mMAFt2	eyJpdiI6Im1JZ3BMYkxBSWhUK0lEeXFUTVAzVVE9PSIsInZhbHVlIjoicExlN1ppeGNmNXRialMva0dBMGVVOEZ2TW9mZkVwdDFXUkxSUDJVTDhVR0luUDh0Nzd3SG8yV3Y5Nnlmdi96RWQvckorcjYwdC9HTU1pekJhMjZYT1k2dTRyaE1PSnR2WUFjZjNIMnAzVjBsZGp0cDJlR0R4dEsvQXVGWHZWQXoybEliYnRISENQUi9RM0tYeWd0ZEF4WCtjTUJjblNlWmZxRGZXejExUHVuT25Gb0tSYzZCUDBFWmJyWlNFQjRMWGtJajJnY09CYkk4MWVVcnZqK3FvcUsvOUx5MytBU2FYUlpFQjhEdzh2ST0iLCJtYWMiOiI0NzY4ODQyZjZjMDI2OTYwYzhiYWE0YTI3NDQ0NWU1YjQyMWYwNmVmN2M3ODZhYjJkMDhkNzFhYjI3MWM1ZmI1IiwidGFnIjoiIn0=	\N	0	\N	2026-08-24 05:10:31	2026-08-24 04:57:48	2026-08-24 04:55:31	2026-08-24 04:57:48
29	tanbm@students.nu-lipa.edu.ph	registration	$2y$12$fV4SnKXcDG6XmPgKM1ibze.VPFN19rMi3cMZaSQ7g5LnGOYEDcXH.	eyJpdiI6IjNEYmkxQ2tKRGRXWHk4bTNCMUhvRGc9PSIsInZhbHVlIjoiRlgvVGU1UnRzYTdMSEcxd2hEekxmREZMeXFNaVdxWlJlV0wrTm0yZDhVK2pQM1g1cHpRblVlanc2T0NlSDM2SjkzQ3poeVNtVXZBQ3gva0RFWTF6VjFWYkFvT1cvV01sVkErN2E4SGZOUHViTCt3aE1teFM2T3cySFAydFR5NjRwQkR3RHpFT3VzalF6bitoZnpJQ1NvWVAyNWdpbEFOdHBEMHlrQ3RZVDZsOElIRzhqWHJIMVR6bUp1UE9tR0xrMGY2T2pxR0NKU1N1V2ErMk4wYUw3d0JLZUE4RE1UeHVKQ29wYlk0cmhwYz0iLCJtYWMiOiJjN2IyYzBlZjQ5ZDAxODFhNTI1YjRlOWU2OTY1MzE4NmYxMTlmNDhmYmNiNDlmZTI2YTM5Mjg0NTc1NTJhY2I1IiwidGFnIjoiIn0=	\N	0	\N	2026-08-24 02:13:05	2026-08-24 01:59:37	2026-08-24 01:58:05	2026-08-24 01:59:37
40	virayrl@students.nu-lipa.edu.ph	registration	$2y$12$Oww1MObMGdfgsV4kbhUv1Os/B5KGKnX7vHoKiNOrK1loCQCVUc/D6	eyJpdiI6Ik5icXJVUmxtTWJoMnpETXBIdWlsR0E9PSIsInZhbHVlIjoiM1NieHJaRlJzMDJWN3JYSTRaTW9vQ3F1cnpDNTZwYW85T3BUbURoZDB6YUVYWFNKLzZ0Sy9iZmI1dlNGZEhqT2d1NVVtYW5ZQnplRWtxTFk4UzV3Q3QrWWdjVUF6UWxXZnJwYXBzT29iUERhS2pyd2FWNFI0NGcvdG1nMGJlUm5wdGh2RE1EWXlkc2FqMnp4UnIxMlcySU52ZnZYQlBsQkUxd1JyNTNYN1VDZGtCVjEzMmttQURhUEs2OWkzdEhNc3p6L0YyQ3dXR1RTWndKNEEwOVFzb2NoanRNcEpYd3QxYVBrTFN0a001OD0iLCJtYWMiOiIyZDA1YjljZWFkOWQwNzFjMjEyYWQ4YmJiY2YwNTBmN2FmMjI0MTE5NTdjMTQxNWNkNzQzZTNhZGUwOTM5Y2VlIiwidGFnIjoiIn0=	121	0	\N	2026-08-24 05:26:55	2026-08-24 05:13:12	2026-08-24 05:11:55	2026-08-24 05:13:13
39	alcantaraka@students.nu-lipa.edu.ph	registration	$2y$12$QXEUnGksOyEWMb3zfymMfeRocNTOMk8cuTa2xAdOuyo7mhq3XqqzS	eyJpdiI6ImdSOGZnYy8wV1J5QXhQUnhMN3FEQmc9PSIsInZhbHVlIjoiZDRQRlhNWGRwUUFZRDMwWEtzQ1VwSzcyMVkzT0hrSWdzbnQ0VFRvZ0x2MndMdmY0aWRTQkZ5bHI2YWI3a3NxcVFSVW02MWtvb3JJTHRuZVREenREb2dOZndPcTNBV1NNek9RcWZXV3F3ZndQM2JOMlZYb0Z3aVgvUG9hUU9EckV6THZES1NnTFpSMnlPcXk0STRhU283WEZhRE4xbzZTMjl6dnhFTVUyUkdpZFdOTG5vQ0UrVXJPK3R4WFBwbHFMQzZQd1diay9ndDVCNjdHUTZaaEhScFM0T21ra1lWZXZQL0lDbUU5cDBKcz0iLCJtYWMiOiJjYTZiOGU5MmNkMWQ2YzlkZjMwNzU4YmNmZjBlMjU1Nzg2M2E2OWU2YjYxMmQyNjIyZjg0ZmQ5OGE0OThhMzJlIiwidGFnIjoiIn0=	\N	0	\N	2026-08-24 05:25:09	2026-08-24 05:12:29	2026-08-24 05:10:09	2026-08-24 05:12:29
52	tanbm@students.nu-lipa.edu.ph	registration	$2y$12$yo6WdSgmRjmKodQZ3A.4v.Tg5Iue.keuf/xWbp7LKPrKLUSSD1IKu	eyJpdiI6ImxiNnBmbW4rT05hVjdOaXFFbEFLZ2c9PSIsInZhbHVlIjoiQTRjU28wQlBTNGFuV2s1aWlHU2t6aGEza2lZZ2phMHFXNHB6VUkrclBoVXdCZEE4MWQzeDNXSkoyYWtSNmlWbXRKcUdQaERjWGl2aW1tRTVFZG9Ic3N1WHcwS0tMS0RuZUMyYnZleTlmTzFpenl4aVBUcVhPSWFRUndmR2c4WjVxSVZpZDRzN2NoTzEvK2w0SmJsRUExTHRtaXlYbjlnSWo2N09xRUhVeDAvY0FndkJ0RFV6SWpCR003ZlpmalRneEcyd1h6ZzZ2T01lTmQ2Q0loS2tWNkc2RkZ5ZStjM2xxc2VPWEpHR0VCQT0iLCJtYWMiOiI0MmJlOGJlM2EwNjdmOTM4MzJiYjBhZDA5NDI3YzBhZmJiMjNiZjVjM2IwMTUxZDc1YTViZDliYzIxZGM0YjE1IiwidGFnIjoiIn0=	\N	0	\N	2026-08-29 07:34:44	\N	2026-08-29 07:19:44	2026-08-29 07:19:44
46	alcantaraka@students.nu-lipa.edu.ph	registration	$2y$12$qWPqLTiFqrRbuBr6oUwxYugIqARtsRS1zZuNaIvVEPZOPH.SsNU/2	eyJpdiI6Ilo3QUdZbEZrK0J6ODQ0c3d6dGxLbXc9PSIsInZhbHVlIjoiL3IzT1NOajd3V0JoN3NEZGE3OEpYNXFWNFh5UUUwMm9CcjVnRzFKYytHbWZLUkNMTXV3UDVqVWR4aWFzTFdxdnBtNzNXQjBpTGI5NFY0eFdoQmRwTGhzY1cvWi9HM2Q3V1VLeC9JVHdHbWtyZnJZUThJMXJWdzBBeHNXaGJ5RDU4Q0x4eW9qL29BTGJ2b3hubVR5NE0ySXMwVllLZmJkNCtLTE01RFFLTGM4bEZpZTF2M28zcnZSdk4rWWV1MG8vWkRnditaaWRWeWFHeFRFcXVubzRtUG9WdGlQVURXYm1ia2dKNmJYd1JOST0iLCJtYWMiOiI0NzgxMDlhNDQ5MmU4M2E5MzQwNjQ4NmMwN2VkY2ZkODU5NWY3ZGMwZmRiZjg1N2ViYjdhNGY0NjVkODBiMzJmIiwidGFnIjoiIn0=	\N	1	\N	2026-08-24 08:00:54	2026-08-24 07:47:20	2026-08-24 07:45:54	2026-08-24 07:47:21
47	alcantaraka@students.nu-lipa.edu.ph	registration	$2y$12$Z3LJDN6A8e6RRh73CsHzfeX.OT0P4LqLdjru0lqWI9rUg7Zh6DmxK	eyJpdiI6InpqeElOZWgrcHB1ZDRBaDZnZFo0YkE9PSIsInZhbHVlIjoiOUhSQ2wvWGgvY3ZLNGp6aEl2R3RGSjRlT0NsRVB1ZnlMbituVTAwL29pV1lDVHkvMnI4aDFiU0pad0o4VG4xV29EcmY3amx2bXVSQjV4eVJjTFIydnV2SFY4YnArRms0ak5pUkhvNktnQnN4UjRzSTVhc3AwNzNOUHlXdytQYUZtTXBBODJ4RTZhVUpwNEQ1Z29HYkdMU1RQdE01bFJvUitzUnVxUVNwRlNrRVNwekF3TEpEWlMxeWlwTytnWkt4WnJGVS8wSXUxL0tVcDVxb1FyQzFBRVhGZEl5elliSFo1ZkdzcWhNMC9Uaz0iLCJtYWMiOiJjNmZhOTlmMjIzZGU4MGI2Y2EyZTlhNzAzOTI3MGM5ODdlZjhkNWZlN2FlNTE4YjcyZmZlMWMwOGFhZTEwN2UwIiwidGFnIjoiIn0=	\N	0	\N	2026-08-24 08:25:55	2026-08-24 08:16:22	2026-08-24 08:10:55	2026-08-24 08:16:23
48	alcantaraka@students.nu-lipa.edu.ph	registration	$2y$12$/hbUok7jE0693SzwKdQAN.BCf3z5GQcvdK4NKmx9c4b8L5WV.cIcG	eyJpdiI6ImN4TDFkSmF3dy9lZkZ0OEdwT0RvR3c9PSIsInZhbHVlIjoiQmxTblF0UVNLRjIrNTN1WjA2OUNZK0wwOFVLLzk5b2UwV1FjTnRTem9qR0NlOWt3U215ai9jRnV0UlNseXFRSXQyVXprTkZHQWwrWndTTlRnZEdwd3NjY09ISHJTbFMzaGh6dDRNSFpEUWtKTWJpcHJkMllOSjExcWxrZ3ZBV0lXaXE2T3dUQWtXVkRiTHlJMkQ2emN0NFpUR1ljMDZDZStWWW5IQU9EZDJLVUUyaXptMTVjTVNqM1VPaVFIZWNmNVZ3ZVc3WkFCSkkrOXJXZnZGaFZBNkN3NnUxTEtPM2I3WW9RbXVXdlpPST0iLCJtYWMiOiIwMTc4ZDliMTZjYjQ5ZTA5ZTAzNGI3ZTE4MTdhYzc3YTg2ZTBmNmE0ZjcxYzIxMjA2ZjFjMDBmMmFjNzFlODlhIiwidGFnIjoiIn0=	\N	0	\N	2026-08-24 09:14:43	2026-08-24 09:05:20	2026-08-24 08:59:43	2026-08-24 09:05:21
51	hernandezgv@students.nu-lipa.edu.ph	registration	$2y$12$5G.VJdzK2LydUrR21OAfa.H8UuXtDhU5COVK.96ApIPcPG28SG9su	eyJpdiI6IjZ5N3dCQWZzUDZjdzQzeDFXZzB2eUE9PSIsInZhbHVlIjoiMGxOVXJGVk1YVEY0c1JQQzZpdEVqemtxS1VYZnhqcTF4VW1LQU9QdG9ZTnYvVmdIeERvL2M4d1hVV2xGSXNIbktON0haMHBUaEliZlpzT3owSEovRjhkL3hqalh1VFZMUDlrT1RuZ285SWFQQkpGazhLV0lRdUQ5VGhCWURIQ3N3L1JYNnZhSzNyK2k1TDlLSXhQQW1zWllJTU1rd3EzVDd4dnhlVEJPVHlXUkdqbTJodGxpZGV6YjIwMG4xMmNHTDlmRUdWZWJCK2NuUXY4c09iMytoQT09IiwibWFjIjoiMTRkMmU4YjE2YzMyMWJhYTZhZGVhMzIzYTM3ODljOGRhOTg0NzRkOTdhNDkwNGVhYjBjZTVhMjkwMzkzNjFjMSIsInRhZyI6IiJ9	128	0	\N	2026-08-24 14:36:49	2026-08-24 14:22:35	2026-08-24 14:21:49	2026-08-24 14:22:35
56	testing2@students.nu-lipa.edu.ph	registration	$2y$12$O4BOdUGw5Tnfv274UiJllOaEkLkKUw4AmBH2TIu9YZHF.dQ3Un.DO	eyJpdiI6IlF2eWM3TWlWbVdZM2laTmliUysyU3c9PSIsInZhbHVlIjoiVXRnYlNWYS9pMFk5dlUxUWZDY1ozZ1licEJXeGhsQXBFK3QzVkVCMXpJVi9vQ2ZTek8xYVpsUWIvZi9zUGRDcFd4TmhLNWcwLzBhS2RGbFR0THhWRG9DeHpFeFhqb1lNSzhPLzVTeE5abDE4bU9yZ3doUG54cmN2SmFTalhmaUZVL2NKOEpCaCszd2RWN0Y4d0RaTGZXcGlJYVFGb09YRXpEVkt0QndIeHorNGFiQ08ydFFUYklKcFZxajNMTE1KKzQ2c0YwbW9tbEdGamNtOHpYVlJ4dz09IiwibWFjIjoiY2ExZDZkY2NlNDRlNTdjMGExM2UwOGZhODY2YTFjYmVlYjQ2NzVmYjY3MWFkZmFjNGMzYTQ0MDNmZmY3NWI5MCIsInRhZyI6IiJ9	135	0	\N	2026-08-30 04:29:18	2026-08-30 04:14:56	2026-08-30 04:14:18	2026-08-30 04:14:56
54	karltzy@students.nu-lipa.edu.ph	registration	$2y$12$6T6wpVG9UQ7Q9i6.iIFyZuaDBJx.PAbeeCY/Fo.HlT26Pn/FpKf16	eyJpdiI6InlDKys4RmZuLzV0cmZwV09wV1Y3aGc9PSIsInZhbHVlIjoiM29OQjFnTUcya1hSQktqMWpVUVZROTR0ZlhLRmVyWWZ6Y1BjY3JYbmpNZTlCUjNGZDVUVC9PTk9CZWlESlk4WjRmeVB1NnJCRlc1d3V2NHppTWxBRUJiY25hcDVaSDBZbXZCZW00dzBpMnZTZkhuOTdCcW4rSmVtWXRBc2Rqa29XYkVnajZ4blFiQm9FYWpnUzd4cGxSWXZscGlBeXowK2ZRTE9MdFZwMnBTSzYzMkxLM21zd1QzbTd5M2ttd0tKYXJUWWRublZJL0sxRGh1NUZVam9YQT09IiwibWFjIjoiNGQwOWE2NjNiMzNjNTc0NGMwNmIyZjk3ODQ2NWM3MjE2NzQyYjA5NTQ0ZTczNWEwMzU4MmIxZjE5OGIzZmJhYSIsInRhZyI6IiJ9	131	0	\N	2026-08-30 02:44:46	2026-08-30 02:33:50	2026-08-30 02:29:46	2026-08-30 02:33:50
55	testing1@students.nu-lipa.edu.ph	registration	$2y$12$8Bck9YC5VWWVSgzyoZm94e3J7/XgLK5XxNpbEK8uYewNL3zJwIinW	eyJpdiI6Imk3NUlYYjYyWjM3YklxcmhibVZHbEE9PSIsInZhbHVlIjoiSVNxMStLSUk4LzRKZXBqV2dobi92a2dDcWFvajlmV1B0US81ZHIrMUlGakFwUCtVRE5PSStlQmp5WFpVQmw1VG11TEVtZTQreXo5RU5yN1crTWY2dHpXWUprVHBFei9DNDNibS81bW5oNDY3Z1dnblZ6dTNYakJ4RkZBYWRxSVlaZzRwazZBbUJTdktVN0FjV2JwOWZuVi9IT25kTXA2c3N2WjlMaVJCbDBPb2w0Tk1hNkRxU0N4aVQwcjNTTXZlRnc0TkY3WlhTcG4yVVUvSWVJSGZyUT09IiwibWFjIjoiMjUzYWRjZjRhYzI4N2EwMjI2MDY5YzNmNDYyNGY3MjMyMzNjNzM2MGE0NjlmY2IwZTA1YjZiMzhlZTVmNmYxZSIsInRhZyI6IiJ9	133	0	\N	2026-08-30 04:25:21	2026-08-30 04:12:12	2026-08-30 04:10:21	2026-08-30 04:12:12
58	ranoel@students.nu-lipa.edu.ph	registration	$2y$12$I1UoICV8xmj639e1.qNge.Hn8tBumwdpoyiMJVIJYgrg.FdLe7K4O	eyJpdiI6IkUrelR4MGNkTXI3ODBsRGg3Q0hrenc9PSIsInZhbHVlIjoiMzhlV2VTMGtyRVNFa24yQ3hPREVQOE9tVEdiRUdtRVBsZXZqRUVyMlA0TnNacWRHVUJLTmd6UE9WZ3pDT2x6NVBmc25XR2tkZVNsVjhPV0NpQUJJdGlJWXcrZE80bUZ3cWhJZG9mMWY5ZVo3cHdLMkhEUjdtUzg2M1ZlcUtENnpGQlNQRFc3NWVNQnpoYmRyNnNtbEU5dEJxZUZLUDIzWTlqVldudlc1aXVwbXI1ZTBObDllejA5MkE1cHppM09ZQnNBSWNLR01xNS85dCtzQjY0bDcydz09IiwibWFjIjoiNDdjMDE5NmUyNDZjYmQxYTFhYWEzMDVhYzNkOWQ2Zjc3NWE2ZmUzYmVmYzM2NjljNjc5ZTg2MmIxYjdlMzA4OSIsInRhZyI6IiJ9	138	0	\N	2026-08-30 14:54:02	2026-08-30 14:39:50	2026-08-30 14:39:02	2026-08-30 14:39:50
57	gennice@students.nu-lipa.edu.ph	registration	$2y$12$eedwkIzHShy5IfnOMNnFQ.szc86uuGr.MHBCdL3MZgZsHSmXoygUC	eyJpdiI6ImRpWVYwUjI1NFgzQm5EQlZiSHRSN3c9PSIsInZhbHVlIjoib1M4SjNwUVUxY0pHd25KSXpPUk4wWGdmRDY2akNScHZGcm4zUjlTM2tZSHJGLzFlUDVuU2s5anphOVZLSmhZUjcxWGRkdzZWWGhtRkhOUXN4ZzlXTWxRT2xOTkVub1BoVEVOeDdWNHk3TTQ5cGQ0VVl4MnE3NEY1T2o1UjFQcGlMOG5zb0xqbUx0MXUybEhrYTJxTGpJbDloWkx3VUt2L1RLOWtQdmlCUUZmWkd3Z0lQTXhCTVE4V0Vlcy94Ty9pREVDc2pDbldRaWJkMWthbEFVTCtRQT09IiwibWFjIjoiOGY4MTAwMzQxYmE0MDVjYmJlNDMxMzBjMzEwMDFkMDNiMTNkMDliM2FhNDhiYzViN2YwOGRlN2UxMzY4ZWRkNSIsInRhZyI6IiJ9	137	0	\N	2026-08-30 08:37:15	2026-08-30 08:33:10	2026-08-30 08:22:15	2026-08-30 08:33:11
53	alcantaraka@students.nu-lipa.edu.ph	registration	$2y$12$hDaB5UVesHCZU3/.tvzkWe.VRf/QjcZMRGHwkDNxM0XkNGYM/.SKy	eyJpdiI6IitPOFV2dUovZkZYYm9EbzRyWVVEbnc9PSIsInZhbHVlIjoid0FOYVNyQlFPRGx3MU16RE85ZHJjdDBhYmkzWmFUbDlTWko5ZTdtRUtZSk1EUFlZSjFzaGJrOUdYcERXbS9RUzlMUmRWbEF0cVF1WkdsNlpZWWFVUmJueHNsSU5xV3ByZnpnRklYNVR1cDhpYkdiY3JiRWVGeTN2dEE4bHhNL09oRjFoRkZkUlN0YVlRZjVxbVhXZUE1N0xORVpxRTBxenZyQmVGY2ZMeE8xRjE1OXJDSmhwTEkxalhqcVFRYVU5L3NYNjhzVSs2R3Q4V2hhRERVdjB5dVM3RmpmNmhCN05GZmQzU3FjRFZkbz0iLCJtYWMiOiJiZDU0MmM5MTYzZTVmODQ1MTIxNzc1MzgzYzNhMWJhYTI0NGY0ZDVkNGM4OTAwNmQ3ZmNkZWNkZjlhNTEwNGU4IiwidGFnIjoiIn0=	\N	0	\N	2026-08-29 14:21:02	2026-08-29 14:11:34	2026-08-29 14:06:02	2026-08-29 14:11:34
59	alcantaraka@students.nu-lipa.edu.ph	registration	$2y$12$se/5FcilWeYAsgzx7iXWveTZIMMaCYzzYKnnnRLj23GYVxkzqx8oS	eyJpdiI6ImRtSyt4Q1NuY2MxeFROd2JaTnJweGc9PSIsInZhbHVlIjoiN2trM3ZKdHVFRUlLRjBTei9PdldJQmpUM0k0N0wwUVJWZzl5QkhqUjdtbjcvWlRwQm43czVsWWhBS3lFTXBaZWhsKzhZR1hWQUtRQTFlTm9WVURPaWM2VU9sQW0rR3VUZDg3SUZnYW1yU3hUaDhsK2RnV1ZCRWp4QVZiclZ2YzhVS1o3QlFUQ1Bab2p3eG8zTzdSTlpweTRBUUg1NmpKL3ByU2Q4WkUwRVVWQm5QRjJ3aHZjOHVrU0YvYVRZTVR0OEFXSW80RFZZTEZmbW82Sm0wNGpaTzFKem9MZUJ3K2w2WlBUb0MzMHVWaz0iLCJtYWMiOiJiYjFiN2JjMTY3MDM3NmIxNjhmYjJiZDk3YjEzYjE3MjgyNjJjNDU3YzkxZWFiY2EyOTAxMjBjOGE5YWNmZmRlIiwidGFnIjoiIn0=	139	0	\N	2026-09-03 08:12:00	2026-09-03 08:01:26	2026-09-03 07:57:00	2026-09-03 08:01:26
60	gennicemarcaida@students.nu-lipa.edu.ph	registration	$2y$12$YsDJrdvtHsBleq.gpOELOefr3iPTCb6iyMwHA2eddj7tATv6jpZU6	eyJpdiI6ImFzdmxHV3hOMVU2dkNpcDliWi9CVEE9PSIsInZhbHVlIjoiZUs1VmNFaVBVL2xiTUVDZ1krVGJvcHluVFl6cWhDb3dKTHYrazB2LzgxajBRcVlqNlNhdG5RQmpsVHpZTnd4bktRcjdzTko4cGM3NmRoTlhLeDFHYzIxSlVFaEo2TW5pazl0Uyt5ZVBTTnk3TEErNUY4R1JCS1FlQ3dCTlFYZE93UEV6S3p4VEVDbGhBRVNXWE5hb3M1UitXdVF4aG9NcGhtSzZ1Q1BlcjNLWWp6cjBpS3ppbnk3UXAvbTVNVUphR015emc2OEROS2tkb2hmSmtUMDhicW43OWdEQ3NWN2VZQm1ZQllaTDNMVT0iLCJtYWMiOiI5ODRjZDJhNzQ4MjEwYTgzZDIwNzU2YmZhYTZhZGRmZjJhNzUzMWRjYzdhZDVjZGYwZDFjYTBmYTkxNzBlNzk3IiwidGFnIjoiIn0=	141	0	\N	2026-09-05 13:47:08	2026-09-05 13:36:25	2026-09-05 13:32:08	2026-09-05 13:36:25
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
21	69161188-7f2e-42b5-abe6-e9c99d2a68eb	database	default	{"uuid":"69161188-7f2e-42b5-abe6-e9c99d2a68eb","displayName":"App\\\\Mail\\\\EmailVerificationCodeMail","job":"Illuminate\\\\Queue\\\\CallQueuedHandler@call","maxTries":3,"maxExceptions":null,"failOnTimeout":false,"backoff":"10,30,60","timeout":null,"retryUntil":null,"deleteWhenMissingModels":false,"data":{"commandName":"Illuminate\\\\Mail\\\\SendQueuedMailable","command":"O:34:\\"Illuminate\\\\Mail\\\\SendQueuedMailable\\":18:{s:8:\\"mailable\\";O:34:\\"App\\\\Mail\\\\EmailVerificationCodeMail\\":4:{s:4:\\"code\\";s:6:\\"947454\\";s:9:\\"expiresAt\\";O:22:\\"Carbon\\\\CarbonImmutable\\":3:{s:4:\\"date\\";s:26:\\"2026-08-24 14:28:30.000000\\";s:13:\\"timezone_type\\";i:3;s:8:\\"timezone\\";s:3:\\"UTC\\";}s:2:\\"to\\";a:1:{i:0;a:2:{s:4:\\"name\\";N;s:7:\\"address\\";s:35:\\"hernandezgv@students.nu-lipa.edu.ph\\";}}s:6:\\"mailer\\";s:6:\\"resend\\";}s:5:\\"tries\\";i:3;s:7:\\"timeout\\";N;s:13:\\"maxExceptions\\";N;s:17:\\"shouldBeEncrypted\\";b:0;s:3:\\"job\\";N;s:10:\\"connection\\";N;s:5:\\"queue\\";N;s:12:\\"messageGroup\\";N;s:12:\\"deduplicator\\";N;s:13:\\"debounceOwner\\";s:0:\\"\\";s:5:\\"delay\\";N;s:11:\\"afterCommit\\";N;s:10:\\"middleware\\";a:0:{}s:7:\\"chained\\";a:0:{}s:15:\\"chainConnection\\";N;s:10:\\"chainQueue\\";N;s:19:\\"chainCatchCallbacks\\";N;}","batchId":null},"createdAt":1787580810,"delay":null}	Resend\\Exceptions\\ErrorException: API key is invalid in /app/vendor/resend/resend-php/src/Transporters/HttpTransporter.php:120\nStack trace:\n#0 /app/vendor/resend/resend-php/src/Transporters/HttpTransporter.php(47): Resend\\Transporters\\HttpTransporter->throwIfJsonError(Array, '{"statusCode":4...')\n#1 /app/vendor/resend/resend-php/src/Service/Email.php(50): Resend\\Transporters\\HttpTransporter->request(Object(Resend\\ValueObjects\\Transporter\\Payload))\n#2 /app/vendor/resend/resend-php/src/Service/Email.php(62): Resend\\Service\\Email->create(Array, Array)\n#3 /app/vendor/laravel/framework/src/Illuminate/Mail/Transport/ResendTransport.php(103): Resend\\Service\\Email->send(Array)\n#4 /app/vendor/symfony/mailer/Transport/AbstractTransport.php(69): Illuminate\\Mail\\Transport\\ResendTransport->doSend(Object(Symfony\\Component\\Mailer\\SentMessage))\n#5 /app/vendor/laravel/framework/src/Illuminate/Mail/Mailer.php(584): Symfony\\Component\\Mailer\\Transport\\AbstractTransport->send(Object(Symfony\\Component\\Mime\\Email), Object(Symfony\\Component\\Mailer\\DelayedEnvelope))\n#6 /app/vendor/laravel/framework/src/Illuminate/Mail/Mailer.php(331): Illuminate\\Mail\\Mailer->sendSymfonyMessage(Object(Symfony\\Component\\Mime\\Email))\n#7 /app/vendor/laravel/framework/src/Illuminate/Mail/Mailable.php(211): Illuminate\\Mail\\Mailer->send(Object(Closure), Array, Object(Closure))\n#8 /app/vendor/laravel/framework/src/Illuminate/Support/Traits/Localizable.php(21): Illuminate\\Mail\\Mailable->{closure:Illuminate\\Mail\\Mailable::send():204}()\n#9 /app/vendor/laravel/framework/src/Illuminate/Mail/Mailable.php(204): Illuminate\\Mail\\Mailable->withLocale(NULL, Object(Closure))\n#10 /app/vendor/laravel/framework/src/Illuminate/Mail/SendQueuedMailable.php(89): Illuminate\\Mail\\Mailable->send(Object(Illuminate\\Mail\\MailManager))\n#11 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): Illuminate\\Mail\\SendQueuedMailable->handle(Object(Illuminate\\Mail\\MailManager))\n#12 /app/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::{closure:Illuminate\\Container\\BoundMethod::call():35}()\n#13 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#14 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#15 /app/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#16 /app/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(136): Illuminate\\Container\\Container->call(Array)\n#17 /app/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Bus\\Dispatcher->{closure:Illuminate\\Bus\\Dispatcher::dispatchNow():133}(Object(Illuminate\\Mail\\SendQueuedMailable))\n#18 /app/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->{closure:Illuminate\\Pipeline\\Pipeline::prepareDestination():178}(Object(Illuminate\\Mail\\SendQueuedMailable))\n#19 /app/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(140): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#20 /app/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(153): Illuminate\\Bus\\Dispatcher->dispatchNow(Object(Illuminate\\Mail\\SendQueuedMailable), false)\n#21 /app/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Queue\\CallQueuedHandler->{closure:Illuminate\\Queue\\CallQueuedHandler::dispatchThroughMiddleware():146}(Object(Illuminate\\Mail\\SendQueuedMailable))\n#22 /app/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->{closure:Illuminate\\Pipeline\\Pipeline::prepareDestination():178}(Object(Illuminate\\Mail\\SendQueuedMailable))\n#23 /app/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(146): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#24 /app/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(84): Illuminate\\Queue\\CallQueuedHandler->dispatchThroughMiddleware(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(Illuminate\\Mail\\SendQueuedMailable))\n#25 /app/vendor/laravel/framework/src/Illuminate/Queue/Jobs/Job.php(102): Illuminate\\Queue\\CallQueuedHandler->call(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Array)\n#26 /app/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(553): Illuminate\\Queue\\Jobs\\Job->fire()\n#27 /app/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(499): Illuminate\\Queue\\Worker->process('database', Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(Illuminate\\Queue\\WorkerOptions))\n#28 /app/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(245): Illuminate\\Queue\\Worker->runJob(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), 'database', Object(Illuminate\\Queue\\WorkerOptions))\n#29 /app/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(149): Illuminate\\Queue\\Worker->daemon('database', 'default', Object(Illuminate\\Queue\\WorkerOptions))\n#30 /app/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(132): Illuminate\\Queue\\Console\\WorkCommand->runWorker('database', 'default')\n#31 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): Illuminate\\Queue\\Console\\WorkCommand->handle()\n#32 /app/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::{closure:Illuminate\\Container\\BoundMethod::call():35}()\n#33 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#34 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#35 /app/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#36 /app/vendor/laravel/framework/src/Illuminate/Console/Command.php(280): Illuminate\\Container\\Container->call(Array)\n#37 /app/vendor/symfony/console/Command/Command.php(284): Illuminate\\Console\\Command->execute(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#38 /app/vendor/laravel/framework/src/Illuminate/Console/Command.php(249): Symfony\\Component\\Console\\Command\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#39 /app/vendor/symfony/console/Application.php(1144): Illuminate\\Console\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#40 /app/vendor/symfony/console/Application.php(379): Symfony\\Component\\Console\\Application->doRunCommand(Object(Illuminate\\Queue\\Console\\WorkCommand), Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#41 /app/vendor/symfony/console/Application.php(218): Symfony\\Component\\Console\\Application->doRun(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#42 /app/vendor/laravel/framework/src/Illuminate/Foundation/Console/Kernel.php(198): Symfony\\Component\\Console\\Application->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#43 /app/vendor/laravel/framework/src/Illuminate/Foundation/Application.php(1235): Illuminate\\Foundation\\Console\\Kernel->handle(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#44 /app/artisan(16): Illuminate\\Foundation\\Application->handleCommand(Object(Symfony\\Component\\Console\\Input\\ArgvInput))\n#45 {main}\n\nNext Symfony\\Component\\Mailer\\Exception\\TransportException: Request to Resend API failed. Reason: API key is invalid. in /app/vendor/laravel/framework/src/Illuminate/Mail/Transport/ResendTransport.php:118\nStack trace:\n#0 /app/vendor/symfony/mailer/Transport/AbstractTransport.php(69): Illuminate\\Mail\\Transport\\ResendTransport->doSend(Object(Symfony\\Component\\Mailer\\SentMessage))\n#1 /app/vendor/laravel/framework/src/Illuminate/Mail/Mailer.php(584): Symfony\\Component\\Mailer\\Transport\\AbstractTransport->send(Object(Symfony\\Component\\Mime\\Email), Object(Symfony\\Component\\Mailer\\DelayedEnvelope))\n#2 /app/vendor/laravel/framework/src/Illuminate/Mail/Mailer.php(331): Illuminate\\Mail\\Mailer->sendSymfonyMessage(Object(Symfony\\Component\\Mime\\Email))\n#3 /app/vendor/laravel/framework/src/Illuminate/Mail/Mailable.php(211): Illuminate\\Mail\\Mailer->send(Object(Closure), Array, Object(Closure))\n#4 /app/vendor/laravel/framework/src/Illuminate/Support/Traits/Localizable.php(21): Illuminate\\Mail\\Mailable->{closure:Illuminate\\Mail\\Mailable::send():204}()\n#5 /app/vendor/laravel/framework/src/Illuminate/Mail/Mailable.php(204): Illuminate\\Mail\\Mailable->withLocale(NULL, Object(Closure))\n#6 /app/vendor/laravel/framework/src/Illuminate/Mail/SendQueuedMailable.php(89): Illuminate\\Mail\\Mailable->send(Object(Illuminate\\Mail\\MailManager))\n#7 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): Illuminate\\Mail\\SendQueuedMailable->handle(Object(Illuminate\\Mail\\MailManager))\n#8 /app/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::{closure:Illuminate\\Container\\BoundMethod::call():35}()\n#9 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#10 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#11 /app/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#12 /app/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(136): Illuminate\\Container\\Container->call(Array)\n#13 /app/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Bus\\Dispatcher->{closure:Illuminate\\Bus\\Dispatcher::dispatchNow():133}(Object(Illuminate\\Mail\\SendQueuedMailable))\n#14 /app/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->{closure:Illuminate\\Pipeline\\Pipeline::prepareDestination():178}(Object(Illuminate\\Mail\\SendQueuedMailable))\n#15 /app/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(140): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#16 /app/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(153): Illuminate\\Bus\\Dispatcher->dispatchNow(Object(Illuminate\\Mail\\SendQueuedMailable), false)\n#17 /app/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Queue\\CallQueuedHandler->{closure:Illuminate\\Queue\\CallQueuedHandler::dispatchThroughMiddleware():146}(Object(Illuminate\\Mail\\SendQueuedMailable))\n#18 /app/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->{closure:Illuminate\\Pipeline\\Pipeline::prepareDestination():178}(Object(Illuminate\\Mail\\SendQueuedMailable))\n#19 /app/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(146): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#20 /app/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(84): Illuminate\\Queue\\CallQueuedHandler->dispatchThroughMiddleware(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(Illuminate\\Mail\\SendQueuedMailable))\n#21 /app/vendor/laravel/framework/src/Illuminate/Queue/Jobs/Job.php(102): Illuminate\\Queue\\CallQueuedHandler->call(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Array)\n#22 /app/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(553): Illuminate\\Queue\\Jobs\\Job->fire()\n#23 /app/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(499): Illuminate\\Queue\\Worker->process('database', Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(Illuminate\\Queue\\WorkerOptions))\n#24 /app/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(245): Illuminate\\Queue\\Worker->runJob(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), 'database', Object(Illuminate\\Queue\\WorkerOptions))\n#25 /app/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(149): Illuminate\\Queue\\Worker->daemon('database', 'default', Object(Illuminate\\Queue\\WorkerOptions))\n#26 /app/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(132): Illuminate\\Queue\\Console\\WorkCommand->runWorker('database', 'default')\n#27 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): Illuminate\\Queue\\Console\\WorkCommand->handle()\n#28 /app/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::{closure:Illuminate\\Container\\BoundMethod::call():35}()\n#29 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#30 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#31 /app/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#32 /app/vendor/laravel/framework/src/Illuminate/Console/Command.php(280): Illuminate\\Container\\Container->call(Array)\n#33 /app/vendor/symfony/console/Command/Command.php(284): Illuminate\\Console\\Command->execute(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#34 /app/vendor/laravel/framework/src/Illuminate/Console/Command.php(249): Symfony\\Component\\Console\\Command\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#35 /app/vendor/symfony/console/Application.php(1144): Illuminate\\Console\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#36 /app/vendor/symfony/console/Application.php(379): Symfony\\Component\\Console\\Application->doRunCommand(Object(Illuminate\\Queue\\Console\\WorkCommand), Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#37 /app/vendor/symfony/console/Application.php(218): Symfony\\Component\\Console\\Application->doRun(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#38 /app/vendor/laravel/framework/src/Illuminate/Foundation/Console/Kernel.php(198): Symfony\\Component\\Console\\Application->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#39 /app/vendor/laravel/framework/src/Illuminate/Foundation/Application.php(1235): Illuminate\\Foundation\\Console\\Kernel->handle(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#40 /app/artisan(16): Illuminate\\Foundation\\Application->handleCommand(Object(Symfony\\Component\\Console\\Input\\ArgvInput))\n#41 {main}	2026-08-24 14:14:19
22	51c2d6c9-fbd8-4a26-82d2-efcfe48abe07	database	default	{"uuid":"51c2d6c9-fbd8-4a26-82d2-efcfe48abe07","displayName":"App\\\\Notifications\\\\ApproverProvisionedNotification","job":"Illuminate\\\\Queue\\\\CallQueuedHandler@call","maxTries":3,"maxExceptions":null,"failOnTimeout":false,"backoff":"10,30,60","timeout":null,"retryUntil":null,"deleteWhenMissingModels":false,"data":{"commandName":"Illuminate\\\\Notifications\\\\SendQueuedNotifications","command":"O:48:\\"Illuminate\\\\Notifications\\\\SendQueuedNotifications\\":5:{s:11:\\"notifiables\\";O:45:\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\":5:{s:5:\\"class\\";s:15:\\"App\\\\Models\\\\User\\";s:2:\\"id\\";a:1:{i:0;i:129;}s:9:\\"relations\\";a:0:{}s:10:\\"connection\\";s:5:\\"pgsql\\";s:15:\\"collectionClass\\";N;}s:12:\\"notification\\";O:49:\\"App\\\\Notifications\\\\ApproverProvisionedNotification\\":3:{s:4:\\"role\\";E:22:\\"App\\\\Enums\\\\Role:Adviser\\";s:17:\\"temporaryPassword\\";s:8:\\"ict@1234\\";s:2:\\"id\\";s:36:\\"41eec8f4-9dea-4075-b524-f7aaeecd7d61\\";}s:8:\\"channels\\";a:1:{i:0;s:4:\\"mail\\";}s:5:\\"tries\\";i:3;s:10:\\"connection\\";s:8:\\"database\\";}","batchId":null},"createdAt":1787582334,"delay":null}	Illuminate\\Queue\\TimeoutExceededException: App\\Notifications\\ApproverProvisionedNotification has timed out. in /app/vendor/laravel/framework/src/Illuminate/Queue/TimeoutExceededException.php:15\nStack trace:\n#0 /app/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(987): Illuminate\\Queue\\TimeoutExceededException::forJob(Object(Illuminate\\Queue\\Jobs\\DatabaseJob))\n#1 /app/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(290): Illuminate\\Queue\\Worker->timeoutExceededException(Object(Illuminate\\Queue\\Jobs\\DatabaseJob))\n#2 /app/vendor/symfony/mailer/Transport/Smtp/Stream/SocketStream.php(154): Illuminate\\Queue\\Worker->{closure:Illuminate\\Queue\\Worker::registerTimeoutHandler():287}(14, Array)\n#3 [internal function]: Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream->{closure:Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream::initialize():153}(2, 'stream_socket_c...', '/app/vendor/sym...', 157)\n#4 /app/vendor/symfony/mailer/Transport/Smtp/Stream/SocketStream.php(157): stream_socket_client('smtp.gmail.com:...', 0, '', 60.0, 4, Resource id #1412)\n#5 /app/vendor/symfony/mailer/Transport/Smtp/SmtpTransport.php(268): Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream->initialize()\n#6 /app/vendor/symfony/mailer/Transport/Smtp/SmtpTransport.php(200): Symfony\\Component\\Mailer\\Transport\\Smtp\\SmtpTransport->start()\n#7 /app/vendor/symfony/mailer/Transport/AbstractTransport.php(69): Symfony\\Component\\Mailer\\Transport\\Smtp\\SmtpTransport->doSend(Object(Symfony\\Component\\Mailer\\SentMessage))\n#8 /app/vendor/symfony/mailer/Transport/Smtp/SmtpTransport.php(138): Symfony\\Component\\Mailer\\Transport\\AbstractTransport->send(Object(Symfony\\Component\\Mime\\Email), Object(Symfony\\Component\\Mailer\\DelayedEnvelope))\n#9 /app/vendor/laravel/framework/src/Illuminate/Mail/Mailer.php(584): Symfony\\Component\\Mailer\\Transport\\Smtp\\SmtpTransport->send(Object(Symfony\\Component\\Mime\\Email), Object(Symfony\\Component\\Mailer\\DelayedEnvelope))\n#10 /app/vendor/laravel/framework/src/Illuminate/Mail/Mailer.php(331): Illuminate\\Mail\\Mailer->sendSymfonyMessage(Object(Symfony\\Component\\Mime\\Email))\n#11 /app/vendor/laravel/framework/src/Illuminate/Mail/Mailable.php(211): Illuminate\\Mail\\Mailer->send(Object(Closure), Array, Object(Closure))\n#12 /app/vendor/laravel/framework/src/Illuminate/Support/Traits/Localizable.php(21): Illuminate\\Mail\\Mailable->{closure:Illuminate\\Mail\\Mailable::send():204}()\n#13 /app/vendor/laravel/framework/src/Illuminate/Mail/Mailable.php(204): Illuminate\\Mail\\Mailable->withLocale(NULL, Object(Closure))\n#14 /app/vendor/laravel/framework/src/Illuminate/Notifications/Channels/MailChannel.php(63): Illuminate\\Mail\\Mailable->send(Object(Illuminate\\Mail\\MailManager))\n#15 /app/vendor/laravel/framework/src/Illuminate/Notifications/NotificationSender.php(165): Illuminate\\Notifications\\Channels\\MailChannel->send(Object(App\\Models\\User), Object(App\\Notifications\\ApproverProvisionedNotification))\n#16 /app/vendor/laravel/framework/src/Illuminate/Notifications/NotificationSender.php(120): Illuminate\\Notifications\\NotificationSender->sendToNotifiable(Object(App\\Models\\User), '6441c043-66f0-4...', Object(App\\Notifications\\ApproverProvisionedNotification), 'mail')\n#17 /app/vendor/laravel/framework/src/Illuminate/Support/Traits/Localizable.php(21): Illuminate\\Notifications\\NotificationSender->{closure:Illuminate\\Notifications\\NotificationSender::sendNow():115}()\n#18 /app/vendor/laravel/framework/src/Illuminate/Notifications/NotificationSender.php(115): Illuminate\\Notifications\\NotificationSender->withLocale(NULL, Object(Closure))\n#19 /app/vendor/laravel/framework/src/Illuminate/Notifications/ChannelManager.php(61): Illuminate\\Notifications\\NotificationSender->sendNow(Object(Illuminate\\Database\\Eloquent\\Collection), Object(App\\Notifications\\ApproverProvisionedNotification), Array)\n#20 /app/vendor/laravel/framework/src/Illuminate/Notifications/SendQueuedNotifications.php(130): Illuminate\\Notifications\\ChannelManager->sendNow(Object(Illuminate\\Database\\Eloquent\\Collection), Object(App\\Notifications\\ApproverProvisionedNotification), Array)\n#21 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): Illuminate\\Notifications\\SendQueuedNotifications->handle(Object(Illuminate\\Notifications\\ChannelManager))\n#22 /app/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::{closure:Illuminate\\Container\\BoundMethod::call():35}()\n#23 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#24 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#25 /app/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#26 /app/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(136): Illuminate\\Container\\Container->call(Array)\n#27 /app/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Bus\\Dispatcher->{closure:Illuminate\\Bus\\Dispatcher::dispatchNow():133}(Object(Illuminate\\Notifications\\SendQueuedNotifications))\n#28 /app/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->{closure:Illuminate\\Pipeline\\Pipeline::prepareDestination():178}(Object(Illuminate\\Notifications\\SendQueuedNotifications))\n#29 /app/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(140): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#30 /app/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(153): Illuminate\\Bus\\Dispatcher->dispatchNow(Object(Illuminate\\Notifications\\SendQueuedNotifications), false)\n#31 /app/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Queue\\CallQueuedHandler->{closure:Illuminate\\Queue\\CallQueuedHandler::dispatchThroughMiddleware():146}(Object(Illuminate\\Notifications\\SendQueuedNotifications))\n#32 /app/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->{closure:Illuminate\\Pipeline\\Pipeline::prepareDestination():178}(Object(Illuminate\\Notifications\\SendQueuedNotifications))\n#33 /app/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(146): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#34 /app/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(84): Illuminate\\Queue\\CallQueuedHandler->dispatchThroughMiddleware(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(Illuminate\\Notifications\\SendQueuedNotifications))\n#35 /app/vendor/laravel/framework/src/Illuminate/Queue/Jobs/Job.php(102): Illuminate\\Queue\\CallQueuedHandler->call(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Array)\n#36 /app/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(553): Illuminate\\Queue\\Jobs\\Job->fire()\n#37 /app/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(499): Illuminate\\Queue\\Worker->process('database', Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(Illuminate\\Queue\\WorkerOptions))\n#38 /app/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(245): Illuminate\\Queue\\Worker->runJob(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), 'database', Object(Illuminate\\Queue\\WorkerOptions))\n#39 /app/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(149): Illuminate\\Queue\\Worker->daemon('database', 'default', Object(Illuminate\\Queue\\WorkerOptions))\n#40 /app/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(132): Illuminate\\Queue\\Console\\WorkCommand->runWorker('database', 'default')\n#41 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): Illuminate\\Queue\\Console\\WorkCommand->handle()\n#42 /app/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::{closure:Illuminate\\Container\\BoundMethod::call():35}()\n#43 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#44 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#45 /app/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#46 /app/vendor/laravel/framework/src/Illuminate/Console/Command.php(280): Illuminate\\Container\\Container->call(Array)\n#47 /app/vendor/symfony/console/Command/Command.php(284): Illuminate\\Console\\Command->execute(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#48 /app/vendor/laravel/framework/src/Illuminate/Console/Command.php(249): Symfony\\Component\\Console\\Command\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#49 /app/vendor/symfony/console/Application.php(1144): Illuminate\\Console\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#50 /app/vendor/symfony/console/Application.php(379): Symfony\\Component\\Console\\Application->doRunCommand(Object(Illuminate\\Queue\\Console\\WorkCommand), Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#51 /app/vendor/symfony/console/Application.php(218): Symfony\\Component\\Console\\Application->doRun(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#52 /app/vendor/laravel/framework/src/Illuminate/Foundation/Console/Kernel.php(198): Symfony\\Component\\Console\\Application->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#53 /app/vendor/laravel/framework/src/Illuminate/Foundation/Application.php(1235): Illuminate\\Foundation\\Console\\Kernel->handle(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#54 /app/artisan(16): Illuminate\\Foundation\\Application->handleCommand(Object(Symfony\\Component\\Console\\Input\\ArgvInput))\n#55 {main}	2026-08-24 14:42:58
23	b6bb1b13-28d9-4d7a-83e7-2cfcfa114fdc	database	default	{"uuid":"b6bb1b13-28d9-4d7a-83e7-2cfcfa114fdc","displayName":"App\\\\Notifications\\\\ApproverHandOffNotification","job":"Illuminate\\\\Queue\\\\CallQueuedHandler@call","maxTries":3,"maxExceptions":null,"failOnTimeout":false,"backoff":"10,30,60","timeout":null,"retryUntil":null,"deleteWhenMissingModels":false,"data":{"commandName":"Illuminate\\\\Notifications\\\\SendQueuedNotifications","command":"O:48:\\"Illuminate\\\\Notifications\\\\SendQueuedNotifications\\":5:{s:11:\\"notifiables\\";O:45:\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\":5:{s:5:\\"class\\";s:15:\\"App\\\\Models\\\\User\\";s:2:\\"id\\";a:1:{i:0;i:38;}s:9:\\"relations\\";a:0:{}s:10:\\"connection\\";s:5:\\"pgsql\\";s:15:\\"collectionClass\\";N;}s:12:\\"notification\\";O:45:\\"App\\\\Notifications\\\\ApproverHandOffNotification\\":4:{s:8:\\"document\\";O:45:\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\":5:{s:5:\\"class\\";s:19:\\"App\\\\Models\\\\Document\\";s:2:\\"id\\";i:80;s:9:\\"relations\\";a:3:{i:0;s:12:\\"organization\\";i:1;s:16:\\"activityProposal\\";i:2;s:33:\\"activityProposal.calendarActivity\\";}s:10:\\"connection\\";s:5:\\"pgsql\\";s:15:\\"collectionClass\\";N;}s:12:\\"stepPosition\\";i:2;s:13:\\"triggerAction\\";E:35:\\"App\\\\Enums\\\\TransitionAction:Advanced\\";s:2:\\"id\\";s:36:\\"772c78c2-bb62-42bb-ae8b-ea8f9e391b28\\";}s:8:\\"channels\\";a:1:{i:0;s:4:\\"mail\\";}s:5:\\"tries\\";i:3;s:10:\\"connection\\";s:8:\\"database\\";}","batchId":null},"createdAt":1787588736,"delay":null}	Illuminate\\Queue\\TimeoutExceededException: App\\Notifications\\ApproverHandOffNotification has timed out. in /app/vendor/laravel/framework/src/Illuminate/Queue/TimeoutExceededException.php:15\nStack trace:\n#0 /app/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(987): Illuminate\\Queue\\TimeoutExceededException::forJob(Object(Illuminate\\Queue\\Jobs\\DatabaseJob))\n#1 /app/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(290): Illuminate\\Queue\\Worker->timeoutExceededException(Object(Illuminate\\Queue\\Jobs\\DatabaseJob))\n#2 /app/vendor/symfony/mailer/Transport/Smtp/Stream/SocketStream.php(154): Illuminate\\Queue\\Worker->{closure:Illuminate\\Queue\\Worker::registerTimeoutHandler():287}(14, Array)\n#3 [internal function]: Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream->{closure:Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream::initialize():153}(2, 'stream_socket_c...', '/app/vendor/sym...', 157)\n#4 /app/vendor/symfony/mailer/Transport/Smtp/Stream/SocketStream.php(157): stream_socket_client('smtp.gmail.com:...', 0, '', 60.0, 4, Resource id #1422)\n#5 /app/vendor/symfony/mailer/Transport/Smtp/SmtpTransport.php(268): Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream->initialize()\n#6 /app/vendor/symfony/mailer/Transport/Smtp/SmtpTransport.php(200): Symfony\\Component\\Mailer\\Transport\\Smtp\\SmtpTransport->start()\n#7 /app/vendor/symfony/mailer/Transport/AbstractTransport.php(69): Symfony\\Component\\Mailer\\Transport\\Smtp\\SmtpTransport->doSend(Object(Symfony\\Component\\Mailer\\SentMessage))\n#8 /app/vendor/symfony/mailer/Transport/Smtp/SmtpTransport.php(138): Symfony\\Component\\Mailer\\Transport\\AbstractTransport->send(Object(Symfony\\Component\\Mime\\Email), Object(Symfony\\Component\\Mailer\\DelayedEnvelope))\n#9 /app/vendor/laravel/framework/src/Illuminate/Mail/Mailer.php(584): Symfony\\Component\\Mailer\\Transport\\Smtp\\SmtpTransport->send(Object(Symfony\\Component\\Mime\\Email), Object(Symfony\\Component\\Mailer\\DelayedEnvelope))\n#10 /app/vendor/laravel/framework/src/Illuminate/Mail/Mailer.php(331): Illuminate\\Mail\\Mailer->sendSymfonyMessage(Object(Symfony\\Component\\Mime\\Email))\n#11 /app/vendor/laravel/framework/src/Illuminate/Mail/Mailable.php(211): Illuminate\\Mail\\Mailer->send(Object(Closure), Array, Object(Closure))\n#12 /app/vendor/laravel/framework/src/Illuminate/Support/Traits/Localizable.php(21): Illuminate\\Mail\\Mailable->{closure:Illuminate\\Mail\\Mailable::send():204}()\n#13 /app/vendor/laravel/framework/src/Illuminate/Mail/Mailable.php(204): Illuminate\\Mail\\Mailable->withLocale(NULL, Object(Closure))\n#14 /app/vendor/laravel/framework/src/Illuminate/Notifications/Channels/MailChannel.php(63): Illuminate\\Mail\\Mailable->send(Object(Illuminate\\Mail\\MailManager))\n#15 /app/vendor/laravel/framework/src/Illuminate/Notifications/NotificationSender.php(165): Illuminate\\Notifications\\Channels\\MailChannel->send(Object(App\\Models\\User), Object(App\\Notifications\\ApproverHandOffNotification))\n#16 /app/vendor/laravel/framework/src/Illuminate/Notifications/NotificationSender.php(120): Illuminate\\Notifications\\NotificationSender->sendToNotifiable(Object(App\\Models\\User), 'eeec2f37-d6dc-4...', Object(App\\Notifications\\ApproverHandOffNotification), 'mail')\n#17 /app/vendor/laravel/framework/src/Illuminate/Support/Traits/Localizable.php(21): Illuminate\\Notifications\\NotificationSender->{closure:Illuminate\\Notifications\\NotificationSender::sendNow():115}()\n#18 /app/vendor/laravel/framework/src/Illuminate/Notifications/NotificationSender.php(115): Illuminate\\Notifications\\NotificationSender->withLocale(NULL, Object(Closure))\n#19 /app/vendor/laravel/framework/src/Illuminate/Notifications/ChannelManager.php(61): Illuminate\\Notifications\\NotificationSender->sendNow(Object(Illuminate\\Database\\Eloquent\\Collection), Object(App\\Notifications\\ApproverHandOffNotification), Array)\n#20 /app/vendor/laravel/framework/src/Illuminate/Notifications/SendQueuedNotifications.php(130): Illuminate\\Notifications\\ChannelManager->sendNow(Object(Illuminate\\Database\\Eloquent\\Collection), Object(App\\Notifications\\ApproverHandOffNotification), Array)\n#21 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): Illuminate\\Notifications\\SendQueuedNotifications->handle(Object(Illuminate\\Notifications\\ChannelManager))\n#22 /app/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::{closure:Illuminate\\Container\\BoundMethod::call():35}()\n#23 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#24 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#25 /app/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#26 /app/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(136): Illuminate\\Container\\Container->call(Array)\n#27 /app/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Bus\\Dispatcher->{closure:Illuminate\\Bus\\Dispatcher::dispatchNow():133}(Object(Illuminate\\Notifications\\SendQueuedNotifications))\n#28 /app/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->{closure:Illuminate\\Pipeline\\Pipeline::prepareDestination():178}(Object(Illuminate\\Notifications\\SendQueuedNotifications))\n#29 /app/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(140): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#30 /app/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(153): Illuminate\\Bus\\Dispatcher->dispatchNow(Object(Illuminate\\Notifications\\SendQueuedNotifications), false)\n#31 /app/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Queue\\CallQueuedHandler->{closure:Illuminate\\Queue\\CallQueuedHandler::dispatchThroughMiddleware():146}(Object(Illuminate\\Notifications\\SendQueuedNotifications))\n#32 /app/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->{closure:Illuminate\\Pipeline\\Pipeline::prepareDestination():178}(Object(Illuminate\\Notifications\\SendQueuedNotifications))\n#33 /app/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(146): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#34 /app/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(84): Illuminate\\Queue\\CallQueuedHandler->dispatchThroughMiddleware(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(Illuminate\\Notifications\\SendQueuedNotifications))\n#35 /app/vendor/laravel/framework/src/Illuminate/Queue/Jobs/Job.php(102): Illuminate\\Queue\\CallQueuedHandler->call(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Array)\n#36 /app/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(553): Illuminate\\Queue\\Jobs\\Job->fire()\n#37 /app/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(499): Illuminate\\Queue\\Worker->process('database', Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(Illuminate\\Queue\\WorkerOptions))\n#38 /app/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(245): Illuminate\\Queue\\Worker->runJob(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), 'database', Object(Illuminate\\Queue\\WorkerOptions))\n#39 /app/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(149): Illuminate\\Queue\\Worker->daemon('database', 'default', Object(Illuminate\\Queue\\WorkerOptions))\n#40 /app/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(132): Illuminate\\Queue\\Console\\WorkCommand->runWorker('database', 'default')\n#41 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): Illuminate\\Queue\\Console\\WorkCommand->handle()\n#42 /app/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::{closure:Illuminate\\Container\\BoundMethod::call():35}()\n#43 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#44 /app/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#45 /app/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#46 /app/vendor/laravel/framework/src/Illuminate/Console/Command.php(280): Illuminate\\Container\\Container->call(Array)\n#47 /app/vendor/symfony/console/Command/Command.php(284): Illuminate\\Console\\Command->execute(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#48 /app/vendor/laravel/framework/src/Illuminate/Console/Command.php(249): Symfony\\Component\\Console\\Command\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#49 /app/vendor/symfony/console/Application.php(1144): Illuminate\\Console\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#50 /app/vendor/symfony/console/Application.php(379): Symfony\\Component\\Console\\Application->doRunCommand(Object(Illuminate\\Queue\\Console\\WorkCommand), Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#51 /app/vendor/symfony/console/Application.php(218): Symfony\\Component\\Console\\Application->doRun(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#52 /app/vendor/laravel/framework/src/Illuminate/Foundation/Console/Kernel.php(198): Symfony\\Component\\Console\\Application->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#53 /app/vendor/laravel/framework/src/Illuminate/Foundation/Application.php(1235): Illuminate\\Foundation\\Console\\Kernel->handle(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#54 /app/artisan(16): Illuminate\\Foundation\\Application->handleCommand(Object(Symfony\\Component\\Console\\Input\\ArgvInput))\n#55 {main}	2026-08-24 16:29:46
\.


--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2024_01_01_000000_create_passkeys_table	1
5	2025_08_14_170933_add_two_factor_columns_to_users_table	1
6	2026_06_20_050415_create_schools_table	1
7	2026_06_20_050416_create_programs_table	1
8	2026_06_20_050417_create_organizations_table	1
9	2026_06_20_050418_create_role_assignments_table	1
10	2026_06_20_055938_create_workflow_templates_table	1
11	2026_06_20_055939_create_workflow_steps_table	1
12	2026_06_20_055940_create_documents_table	1
13	2026_06_20_055941_create_document_step_approvals_table	1
14	2026_06_20_055942_create_document_transitions_table	1
15	2026_06_20_055943_create_approval_notifications_table	1
16	2026_07_06_065200_create_organization_memberships_table	1
17	2026_07_06_065200_create_organization_registration_details_table	1
18	2026_07_06_095705_create_activity_calendars_table	1
19	2026_07_06_095706_create_calendar_activities_table	1
20	2026_07_06_105617_create_activity_proposals_table	1
21	2026_07_06_105618_create_activity_proposal_attachments_table	1
22	2026_07_08_030559_add_academic_year_to_organization_registration_details_table	1
23	2026_07_08_030600_create_after_activity_reports_table	1
24	2026_07_08_030601_create_after_activity_report_attachments_table	1
25	2026_07_09_080734_add_account_status_to_users_table	1
26	2026_07_10_015307_create_settings_table	1
27	2026_07_10_024805_add_sdg_participant_program_assigned_budget_to_calendar_activities_table	1
28	2026_07_10_072632_rename_contact_and_description_fields_on_organization_registration_details_table	1
29	2026_07_10_081137_rename_and_extend_after_activity_reports_table	1
30	2026_07_10_121110_rename_and_extend_activity_proposals_table	1
31	2026_07_11_023940_add_step_two_narrative_fields_to_activity_proposals	1
32	2026_07_11_031640_create_document_attachments_table	1
33	2026_07_11_031641_drop_form_specific_attachment_tables	1
34	2026_07_11_031641_drop_roster_from_organization_registration_details_table	1
35	2026_07_11_084910_add_flagged_sections_to_document_transitions_table	1
36	2026_07_21_053106_change_role_assignments_organization_id_to_null_on_delete	2
37	2026_07_27_173915_add_section_comments_to_document_transitions_table	2
38	2026_08_05_164210_add_expense_items_to_activity_proposals_table	2
39	2026_08_12_170739_create_email_verification_codes_table	2
40	2026_08_12_170844_add_id_number_to_users_table	2
41	2026_08_13_022011_create_notifications_table	2
42	2026_08_13_165438_create_organization_join_requests_table	2
43	2026_08_15_152419_add_field_changes_to_document_transitions_table	2
44	2026_08_29_130000_make_organizations_school_id_nullable	3
45	2026_08_29_120000_dedupe_schools_and_add_unique_name_index	4
46	2026_09_05_100000_add_term_and_coverage_to_organization_registration_details_table	5
47	2026_09_05_100100_backfill_period_and_coverage_on_organization_registration_details_table	5
48	2026_09_05_100200_migrate_current_term_setting_to_current_period	5
\.


--
-- Data for Name: notifications; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.notifications (id, type, notifiable_type, notifiable_id, data, read_at, created_at, updated_at) FROM stdin;
9b33e1f3-5c1f-43cf-9664-75c948015471	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 CODECS (2026-2027)","body":"Organization Registration \\u2022 CODECS","url":"\\/review\\/registrations\\/35","document_id":35,"form_type":"organization_registration","organization":"CODECS","status":null}	\N	2026-08-21 05:32:59	2026-08-21 05:32:59
6bac7b71-eeb3-41b4-86e1-348e3abd1d6a	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 CODECS (2026-2027)","body":"Organization Registration \\u2022 CODECS","url":"\\/review\\/registrations\\/35","document_id":35,"form_type":"organization_registration","organization":"CODECS","status":null}	\N	2026-08-21 05:33:04	2026-08-21 05:33:04
f4577c40-913f-4668-a9f7-60f73a410203	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 CODECS (2026-2027)","body":"Organization Registration \\u2022 CODECS","url":"\\/review\\/registrations\\/35","document_id":35,"form_type":"organization_registration","organization":"CODECS","status":null}	\N	2026-08-21 05:33:14	2026-08-21 05:33:14
5b4fe582-6210-4e48-8c71-9ef7b277260b	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	91	{"kind":"document_outcome","title":"Organization Registration \\u2014 CODECS (2026-2027) was approved","body":"Organization Registration \\u2022 CODECS","url":"\\/registrations\\/35","document_id":35,"form_type":"organization_registration","organization":"CODECS","status":"approved"}	\N	2026-08-21 05:33:46	2026-08-21 05:33:46
6a34cbf1-8ebf-4806-8570-fb63c49da213	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 UAPSA (2026-2027)","body":"Organization Registration \\u2022 UAPSA","url":"\\/review\\/registrations\\/36","document_id":36,"form_type":"organization_registration","organization":"UAPSA","status":null}	\N	2026-08-21 05:34:25	2026-08-21 05:34:25
82d90a4b-ac27-4f98-9ccf-f3e03461c1c0	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 UAPSA (2026-2027)","body":"Organization Registration \\u2022 UAPSA","url":"\\/review\\/registrations\\/36","document_id":36,"form_type":"organization_registration","organization":"UAPSA","status":null}	\N	2026-08-21 05:34:30	2026-08-21 05:34:30
bc34edd1-5500-4841-8964-bcfeb845e4f6	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 UAPSA (2026-2027)","body":"Organization Registration \\u2022 UAPSA","url":"\\/review\\/registrations\\/36","document_id":36,"form_type":"organization_registration","organization":"UAPSA","status":null}	\N	2026-08-21 05:34:40	2026-08-21 05:34:40
411e3e2d-54c0-4219-8a9f-972b077ab919	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	92	{"kind":"document_outcome","title":"Organization Registration \\u2014 UAPSA (2026-2027) was approved","body":"Organization Registration \\u2022 UAPSA","url":"\\/registrations\\/36","document_id":36,"form_type":"organization_registration","organization":"UAPSA","status":"approved"}	\N	2026-08-21 05:35:12	2026-08-21 05:35:12
6654e9a2-e125-4a15-9bcd-e53fc2476486	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 PICE (2026-2027)","body":"Organization Registration \\u2022 PICE","url":"\\/review\\/registrations\\/37","document_id":37,"form_type":"organization_registration","organization":"PICE","status":null}	\N	2026-08-21 05:35:50	2026-08-21 05:35:50
d1f21456-a4eb-41e2-97b4-bdda24216394	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 PICE (2026-2027)","body":"Organization Registration \\u2022 PICE","url":"\\/review\\/registrations\\/37","document_id":37,"form_type":"organization_registration","organization":"PICE","status":null}	\N	2026-08-21 05:35:56	2026-08-21 05:35:56
6de74ef4-6aea-4d51-a80f-cdebabe35e6e	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 PICE (2026-2027)","body":"Organization Registration \\u2022 PICE","url":"\\/review\\/registrations\\/37","document_id":37,"form_type":"organization_registration","organization":"PICE","status":null}	\N	2026-08-21 05:36:06	2026-08-21 05:36:06
d6165f50-7131-485f-9a55-c4d99bd41902	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	93	{"kind":"document_outcome","title":"Organization Registration \\u2014 PICE (2026-2027) was approved","body":"Organization Registration \\u2022 PICE","url":"\\/registrations\\/37","document_id":37,"form_type":"organization_registration","organization":"PICE","status":"approved"}	\N	2026-08-21 05:36:38	2026-08-21 05:36:38
d3b8faac-ec8e-4f60-a7e8-cc1f6910c383	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Psychology Org (2026-2027)","body":"Organization Registration \\u2022 Psychology Org","url":"\\/review\\/registrations\\/38","document_id":38,"form_type":"organization_registration","organization":"Psychology Org","status":null}	\N	2026-08-21 05:37:16	2026-08-21 05:37:16
c4539b8c-a7c2-409f-b404-b036cbb2b167	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Psychology Org (2026-2027)","body":"Organization Registration \\u2022 Psychology Org","url":"\\/review\\/registrations\\/38","document_id":38,"form_type":"organization_registration","organization":"Psychology Org","status":null}	\N	2026-08-21 05:37:22	2026-08-21 05:37:22
e06000a1-950b-4a14-93ac-401df38675ac	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Psychology Org (2026-2027)","body":"Organization Registration \\u2022 Psychology Org","url":"\\/review\\/registrations\\/38","document_id":38,"form_type":"organization_registration","organization":"Psychology Org","status":null}	\N	2026-08-21 05:37:32	2026-08-21 05:37:32
71e3d2d5-686d-40f4-bd36-809aee0b0da5	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	94	{"kind":"document_outcome","title":"Organization Registration \\u2014 Psychology Org (2026-2027) was approved","body":"Organization Registration \\u2022 Psychology Org","url":"\\/registrations\\/38","document_id":38,"form_type":"organization_registration","organization":"Psychology Org","status":"approved"}	\N	2026-08-21 05:38:04	2026-08-21 05:38:04
a340bc48-8391-4ddc-91ec-225d11a376a9	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 MTSC (2026-2027)","body":"Organization Registration \\u2022 MTSC","url":"\\/review\\/registrations\\/39","document_id":39,"form_type":"organization_registration","organization":"MTSC","status":null}	\N	2026-08-21 05:38:43	2026-08-21 05:38:43
f594945f-fca6-4654-9032-3ecb63dd9284	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 MTSC (2026-2027)","body":"Organization Registration \\u2022 MTSC","url":"\\/review\\/registrations\\/39","document_id":39,"form_type":"organization_registration","organization":"MTSC","status":null}	\N	2026-08-21 05:38:48	2026-08-21 05:38:48
5e639e53-7b84-4516-99d4-a1728a3058ba	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 MTSC (2026-2027)","body":"Organization Registration \\u2022 MTSC","url":"\\/review\\/registrations\\/39","document_id":39,"form_type":"organization_registration","organization":"MTSC","status":null}	\N	2026-08-21 05:38:59	2026-08-21 05:38:59
6e80b1cf-0093-4712-9e4e-2275f5211c50	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	95	{"kind":"document_outcome","title":"Organization Registration \\u2014 MTSC (2026-2027) was approved","body":"Organization Registration \\u2022 MTSC","url":"\\/registrations\\/39","document_id":39,"form_type":"organization_registration","organization":"MTSC","status":"approved"}	\N	2026-08-21 05:39:31	2026-08-21 05:39:31
411941a7-4950-4171-96d0-03df899f5383	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 JPIA (2026-2027)","body":"Organization Registration \\u2022 JPIA","url":"\\/review\\/registrations\\/40","document_id":40,"form_type":"organization_registration","organization":"JPIA","status":null}	\N	2026-08-21 05:40:10	2026-08-21 05:40:10
2f0cd3f8-1fee-4122-b87b-f8fa433940e1	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 JPIA (2026-2027)","body":"Organization Registration \\u2022 JPIA","url":"\\/review\\/registrations\\/40","document_id":40,"form_type":"organization_registration","organization":"JPIA","status":null}	\N	2026-08-21 05:40:15	2026-08-21 05:40:15
70473efb-8384-420e-b8a6-348414433484	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 JPIA (2026-2027)","body":"Organization Registration \\u2022 JPIA","url":"\\/review\\/registrations\\/40","document_id":40,"form_type":"organization_registration","organization":"JPIA","status":null}	\N	2026-08-21 05:40:25	2026-08-21 05:40:25
4fb6dcff-6c38-4a5b-8046-22118e08dfdc	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	96	{"kind":"document_outcome","title":"Organization Registration \\u2014 JPIA (2026-2027) was approved","body":"Organization Registration \\u2022 JPIA","url":"\\/registrations\\/40","document_id":40,"form_type":"organization_registration","organization":"JPIA","status":"approved"}	\N	2026-08-21 05:40:57	2026-08-21 05:40:57
697b24a7-4717-47eb-9222-6f9212e62cbc	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Red Cross Youth (2026-2027)","body":"Organization Registration \\u2022 Red Cross Youth","url":"\\/review\\/registrations\\/41","document_id":41,"form_type":"organization_registration","organization":"Red Cross Youth","status":null}	\N	2026-08-21 05:41:36	2026-08-21 05:41:36
bfca6d5e-03cd-415a-9873-735bb7dd7cd3	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Red Cross Youth (2026-2027)","body":"Organization Registration \\u2022 Red Cross Youth","url":"\\/review\\/registrations\\/41","document_id":41,"form_type":"organization_registration","organization":"Red Cross Youth","status":null}	\N	2026-08-21 05:41:41	2026-08-21 05:41:41
5b1f1ddf-37d6-4495-9ba8-96699401068a	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Red Cross Youth (2026-2027)","body":"Organization Registration \\u2022 Red Cross Youth","url":"\\/review\\/registrations\\/41","document_id":41,"form_type":"organization_registration","organization":"Red Cross Youth","status":null}	\N	2026-08-21 05:41:52	2026-08-21 05:41:52
8db20fef-5b5c-4492-a40b-ac3af73f7c3a	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	97	{"kind":"document_outcome","title":"Organization Registration \\u2014 Red Cross Youth (2026-2027) was approved","body":"Organization Registration \\u2022 Red Cross Youth","url":"\\/registrations\\/41","document_id":41,"form_type":"organization_registration","organization":"Red Cross Youth","status":"approved"}	\N	2026-08-21 05:42:24	2026-08-21 05:42:24
5d3be772-d879-4585-b9b8-c3fcdae66f0b	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Venaris Esports (2026-2027)","body":"Organization Registration \\u2022 Venaris Esports","url":"\\/review\\/registrations\\/42","document_id":42,"form_type":"organization_registration","organization":"Venaris Esports","status":null}	\N	2026-08-21 05:43:03	2026-08-21 05:43:03
30fb1ef9-6119-4af5-89f6-fa200f3ffd1f	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Venaris Esports (2026-2027)","body":"Organization Registration \\u2022 Venaris Esports","url":"\\/review\\/registrations\\/42","document_id":42,"form_type":"organization_registration","organization":"Venaris Esports","status":null}	\N	2026-08-21 05:43:08	2026-08-21 05:43:08
ff6c1f57-79c0-496c-b80e-ca1d417d49a5	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Venaris Esports (2026-2027)","body":"Organization Registration \\u2022 Venaris Esports","url":"\\/review\\/registrations\\/42","document_id":42,"form_type":"organization_registration","organization":"Venaris Esports","status":null}	\N	2026-08-21 05:43:18	2026-08-21 05:43:18
94ac1f6f-8691-4071-a127-689a0387e728	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	98	{"kind":"document_outcome","title":"Organization Registration \\u2014 Venaris Esports (2026-2027) was approved","body":"Organization Registration \\u2022 Venaris Esports","url":"\\/registrations\\/42","document_id":42,"form_type":"organization_registration","organization":"Venaris Esports","status":"approved"}	\N	2026-08-21 05:43:50	2026-08-21 05:43:50
e5aee03c-69da-4208-9664-f02526211f25	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 NEXUS (2026-2027)","body":"Organization Registration \\u2022 NEXUS","url":"\\/review\\/registrations\\/44","document_id":44,"form_type":"organization_registration","organization":"NEXUS","status":null}	\N	2026-08-21 05:44:50	2026-08-21 05:44:50
ded03de0-1c11-4de3-ba28-41e5add7e82b	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 NEXUS (2026-2027)","body":"Organization Registration \\u2022 NEXUS","url":"\\/review\\/registrations\\/44","document_id":44,"form_type":"organization_registration","organization":"NEXUS","status":null}	\N	2026-08-21 05:44:55	2026-08-21 05:44:55
a57a23f4-946b-420c-ac0d-c1aa655bde3d	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 NEXUS (2026-2027)","body":"Organization Registration \\u2022 NEXUS","url":"\\/review\\/registrations\\/44","document_id":44,"form_type":"organization_registration","organization":"NEXUS","status":null}	\N	2026-08-21 05:45:06	2026-08-21 05:45:06
9cf1260f-5d59-4e8a-9602-17ae69b55ead	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 COMEX (2026-2027)","body":"Organization Registration \\u2022 COMEX","url":"\\/review\\/registrations\\/45","document_id":45,"form_type":"organization_registration","organization":"COMEX","status":null}	\N	2026-08-21 05:45:46	2026-08-21 05:45:46
b4e7896e-e2d4-497d-a4b2-c98889b1354e	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 COMEX (2026-2027)","body":"Organization Registration \\u2022 COMEX","url":"\\/review\\/registrations\\/45","document_id":45,"form_type":"organization_registration","organization":"COMEX","status":null}	\N	2026-08-21 05:45:51	2026-08-21 05:45:51
39e404eb-0e56-446c-bc44-dfe3f0f72f32	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 COMEX (2026-2027)","body":"Organization Registration \\u2022 COMEX","url":"\\/review\\/registrations\\/45","document_id":45,"form_type":"organization_registration","organization":"COMEX","status":null}	\N	2026-08-21 05:46:01	2026-08-21 05:46:01
094cfbb6-ada1-4960-b89d-9055a242dde1	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	104	{"kind":"document_outcome","title":"Organization Registration \\u2014 COMEX (2026-2027) was returned for revision","body":"Organization Registration \\u2022 COMEX","url":"\\/registrations\\/45","document_id":45,"form_type":"organization_registration","organization":"COMEX","status":"returned"}	\N	2026-08-21 05:46:15	2026-08-21 05:46:15
73e582b2-6059-49bd-b5c1-0f814c2a40da	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 CREA8ives (2026-2027)","body":"Organization Registration \\u2022 CREA8ives","url":"\\/review\\/registrations\\/46","document_id":46,"form_type":"organization_registration","organization":"CREA8ives","status":null}	\N	2026-08-21 05:46:43	2026-08-21 05:46:43
26c56b56-201e-4e48-a3de-bc4055243919	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 CREA8ives (2026-2027)","body":"Organization Registration \\u2022 CREA8ives","url":"\\/review\\/registrations\\/46","document_id":46,"form_type":"organization_registration","organization":"CREA8ives","status":null}	\N	2026-08-21 05:46:49	2026-08-21 05:46:49
8dff2b6f-7807-4faf-b2eb-eb602e870a10	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 CREA8ives (2026-2027)","body":"Organization Registration \\u2022 CREA8ives","url":"\\/review\\/registrations\\/46","document_id":46,"form_type":"organization_registration","organization":"CREA8ives","status":null}	\N	2026-08-21 05:46:59	2026-08-21 05:46:59
9dbefd5c-aa18-4e58-9fcd-7d740c8d8ab8	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	105	{"kind":"document_outcome","title":"Organization Registration \\u2014 CREA8ives (2026-2027) was rejected","body":"Organization Registration \\u2022 CREA8ives","url":"\\/registrations\\/46","document_id":46,"form_type":"organization_registration","organization":"CREA8ives","status":"rejected"}	\N	2026-08-21 05:47:11	2026-08-21 05:47:11
88b0762a-c57c-4f97-9b75-42baa20f09b4	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 UAPSA (2026-2027)","body":"Organization Renewal \\u2022 UAPSA","url":"\\/review\\/renewals\\/48","document_id":48,"form_type":"organization_renewal","organization":"UAPSA","status":null}	\N	2026-08-21 05:47:53	2026-08-21 05:47:53
22d3f3b8-e0f3-4ca3-a574-a233dc8a0e52	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 UAPSA (2026-2027)","body":"Organization Renewal \\u2022 UAPSA","url":"\\/review\\/renewals\\/48","document_id":48,"form_type":"organization_renewal","organization":"UAPSA","status":null}	\N	2026-08-21 05:47:59	2026-08-21 05:47:59
41b2af3c-48cc-42c7-8f29-decc5ff7f9eb	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 UAPSA (2026-2027)","body":"Organization Renewal \\u2022 UAPSA","url":"\\/review\\/renewals\\/48","document_id":48,"form_type":"organization_renewal","organization":"UAPSA","status":null}	\N	2026-08-21 05:48:09	2026-08-21 05:48:09
321209e2-ef6f-4b3d-aca7-918dc88c7570	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 PICE (2026-2027)","body":"Organization Renewal \\u2022 PICE","url":"\\/review\\/renewals\\/49","document_id":49,"form_type":"organization_renewal","organization":"PICE","status":null}	\N	2026-08-21 05:48:53	2026-08-21 05:48:53
5adaff7b-f832-4d04-aa3f-2870d7e131e2	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 PICE (2026-2027)","body":"Organization Renewal \\u2022 PICE","url":"\\/review\\/renewals\\/49","document_id":49,"form_type":"organization_renewal","organization":"PICE","status":null}	\N	2026-08-21 05:48:58	2026-08-21 05:48:58
b9aae3ff-7d54-41f1-bb0e-9db4ca4c6676	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 PICE (2026-2027)","body":"Organization Renewal \\u2022 PICE","url":"\\/review\\/renewals\\/49","document_id":49,"form_type":"organization_renewal","organization":"PICE","status":null}	\N	2026-08-21 05:49:09	2026-08-21 05:49:09
2bfd74fa-e2ca-4a95-8fb0-047d3a42625b	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	100	{"kind":"document_outcome","title":"Organization Renewal \\u2014 PICE (2026-2027) was returned for revision","body":"Organization Renewal \\u2022 PICE","url":"\\/renewals\\/49","document_id":49,"form_type":"organization_renewal","organization":"PICE","status":"returned"}	\N	2026-08-21 05:49:20	2026-08-21 05:49:20
79bba0f5-37b4-4472-9c85-3e61388cac7a	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	93	{"kind":"document_outcome","title":"Organization Renewal \\u2014 PICE (2026-2027) was returned for revision","body":"Organization Renewal \\u2022 PICE","url":"\\/renewals\\/49","document_id":49,"form_type":"organization_renewal","organization":"PICE","status":"returned"}	\N	2026-08-21 05:49:24	2026-08-21 05:49:24
0329edc4-16cb-4258-9ce0-e1b09756577d	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 JPIA (2026-2027)","body":"Organization Renewal \\u2022 JPIA","url":"\\/review\\/renewals\\/50","document_id":50,"form_type":"organization_renewal","organization":"JPIA","status":null}	\N	2026-08-21 05:50:01	2026-08-21 05:50:01
64686c66-92a1-4bbf-b67a-e7c03ec52674	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 JPIA (2026-2027)","body":"Organization Renewal \\u2022 JPIA","url":"\\/review\\/renewals\\/50","document_id":50,"form_type":"organization_renewal","organization":"JPIA","status":null}	\N	2026-08-21 05:50:06	2026-08-21 05:50:06
914535c2-92af-41e8-9a15-60634391a235	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 JPIA (2026-2027)","body":"Organization Renewal \\u2022 JPIA","url":"\\/review\\/renewals\\/50","document_id":50,"form_type":"organization_renewal","organization":"JPIA","status":null}	\N	2026-08-21 05:50:17	2026-08-21 05:50:17
0acbd293-879c-488d-b41b-ca37e5c174cf	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	101	{"kind":"document_outcome","title":"Organization Renewal \\u2014 JPIA (2026-2027) was approved","body":"Organization Renewal \\u2022 JPIA","url":"\\/renewals\\/50","document_id":50,"form_type":"organization_renewal","organization":"JPIA","status":"approved"}	\N	2026-08-21 05:50:40	2026-08-21 05:50:40
79bffc22-dcac-47db-962f-ea0a488cf126	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	96	{"kind":"document_outcome","title":"Organization Renewal \\u2014 JPIA (2026-2027) was approved","body":"Organization Renewal \\u2022 JPIA","url":"\\/renewals\\/50","document_id":50,"form_type":"organization_renewal","organization":"JPIA","status":"approved"}	\N	2026-08-21 05:50:44	2026-08-21 05:50:44
49bfec68-982f-4ddd-8c63-cb3e7a132c5c	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 Red Cross Youth (2026-2027)","body":"Organization Renewal \\u2022 Red Cross Youth","url":"\\/review\\/renewals\\/51","document_id":51,"form_type":"organization_renewal","organization":"Red Cross Youth","status":null}	\N	2026-08-21 05:51:21	2026-08-21 05:51:21
145ad22f-aa8b-42f8-9454-c2ea79e6f35f	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 Red Cross Youth (2026-2027)","body":"Organization Renewal \\u2022 Red Cross Youth","url":"\\/review\\/renewals\\/51","document_id":51,"form_type":"organization_renewal","organization":"Red Cross Youth","status":null}	\N	2026-08-21 05:51:26	2026-08-21 05:51:26
298a71fc-bbf4-4b48-a2a1-26b4c679e5e3	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 Red Cross Youth (2026-2027)","body":"Organization Renewal \\u2022 Red Cross Youth","url":"\\/review\\/renewals\\/51","document_id":51,"form_type":"organization_renewal","organization":"Red Cross Youth","status":null}	\N	2026-08-21 05:51:37	2026-08-21 05:51:37
bd29cb34-d554-414a-834a-dfc8d99e99d2	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	97	{"kind":"document_outcome","title":"Organization Renewal \\u2014 Red Cross Youth (2026-2027) was rejected","body":"Organization Renewal \\u2022 Red Cross Youth","url":"\\/renewals\\/51","document_id":51,"form_type":"organization_renewal","organization":"Red Cross Youth","status":"rejected"}	\N	2026-08-21 05:51:47	2026-08-21 05:51:47
09a3e8a5-dfde-4c75-8f9c-cb2409b76578	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 CODECS (1st Term 2026-2027)","body":"Activity Calendar \\u2022 CODECS","url":"\\/review\\/activity-calendars\\/52","document_id":52,"form_type":"activity_calendar","organization":"CODECS","status":null}	\N	2026-08-21 05:52:10	2026-08-21 05:52:10
8df66190-c464-49c5-92f7-0b21f11fd358	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 CODECS (1st Term 2026-2027)","body":"Activity Calendar \\u2022 CODECS","url":"\\/review\\/activity-calendars\\/52","document_id":52,"form_type":"activity_calendar","organization":"CODECS","status":null}	\N	2026-08-21 05:52:15	2026-08-21 05:52:15
7d48b6a2-652e-4447-83d5-d3b8a4342c49	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 CODECS (1st Term 2026-2027)","body":"Activity Calendar \\u2022 CODECS","url":"\\/review\\/activity-calendars\\/52","document_id":52,"form_type":"activity_calendar","organization":"CODECS","status":null}	\N	2026-08-21 05:52:25	2026-08-21 05:52:25
6f8f3fab-9e77-41c8-a9f8-09bfb50fc1b7	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	99	{"kind":"document_outcome","title":"Activity Calendar \\u2014 CODECS (1st Term 2026-2027) was approved","body":"Activity Calendar \\u2022 CODECS","url":"\\/activity-calendars\\/52","document_id":52,"form_type":"activity_calendar","organization":"CODECS","status":"approved"}	\N	2026-08-21 05:52:51	2026-08-21 05:52:51
27ac932e-51f0-4529-9a5a-ebf05f35e23a	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	91	{"kind":"document_outcome","title":"Activity Calendar \\u2014 CODECS (1st Term 2026-2027) was approved","body":"Activity Calendar \\u2022 CODECS","url":"\\/activity-calendars\\/52","document_id":52,"form_type":"activity_calendar","organization":"CODECS","status":"approved"}	\N	2026-08-21 05:52:56	2026-08-21 05:52:56
82cd1cfb-d9bc-4c87-89c3-cc546201bfb3	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 UAPSA (1st Term 2026-2027)","body":"Activity Calendar \\u2022 UAPSA","url":"\\/review\\/activity-calendars\\/53","document_id":53,"form_type":"activity_calendar","organization":"UAPSA","status":null}	\N	2026-08-21 05:53:24	2026-08-21 05:53:24
133f1056-3d88-4a62-8cbb-8981af01dc69	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 UAPSA (1st Term 2026-2027)","body":"Activity Calendar \\u2022 UAPSA","url":"\\/review\\/activity-calendars\\/53","document_id":53,"form_type":"activity_calendar","organization":"UAPSA","status":null}	\N	2026-08-21 05:53:35	2026-08-21 05:53:35
0d9e6ce2-5248-4118-8ad5-848ae0c2a28a	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	92	{"kind":"document_outcome","title":"Activity Calendar \\u2014 UAPSA (1st Term 2026-2027) was approved","body":"Activity Calendar \\u2022 UAPSA","url":"\\/activity-calendars\\/53","document_id":53,"form_type":"activity_calendar","organization":"UAPSA","status":"approved"}	\N	2026-08-21 05:54:00	2026-08-21 05:54:00
b6c200fb-5129-4fa9-841c-d4dd8b6a5d7e	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 PICE (1st Term 2026-2027)","body":"Activity Calendar \\u2022 PICE","url":"\\/review\\/activity-calendars\\/54","document_id":54,"form_type":"activity_calendar","organization":"PICE","status":null}	\N	2026-08-21 05:54:28	2026-08-21 05:54:28
81e7d21a-808c-4e82-8117-596166777db1	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 PICE (1st Term 2026-2027)","body":"Activity Calendar \\u2022 PICE","url":"\\/review\\/activity-calendars\\/54","document_id":54,"form_type":"activity_calendar","organization":"PICE","status":null}	\N	2026-08-21 05:54:38	2026-08-21 05:54:38
85a205f9-4408-459b-82ed-e9d8ab6a0f81	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	100	{"kind":"document_outcome","title":"Activity Calendar \\u2014 PICE (1st Term 2026-2027) was approved","body":"Activity Calendar \\u2022 PICE","url":"\\/activity-calendars\\/54","document_id":54,"form_type":"activity_calendar","organization":"PICE","status":"approved"}	\N	2026-08-21 05:55:03	2026-08-21 05:55:03
2bd51f47-af8b-4bbb-8484-cc736ccdb6f5	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	93	{"kind":"document_outcome","title":"Activity Calendar \\u2014 PICE (1st Term 2026-2027) was approved","body":"Activity Calendar \\u2022 PICE","url":"\\/activity-calendars\\/54","document_id":54,"form_type":"activity_calendar","organization":"PICE","status":"approved"}	\N	2026-08-21 05:55:08	2026-08-21 05:55:08
4c87b916-db92-4144-be12-5dbc25f2a373	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Psychology Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Psychology Org","url":"\\/review\\/activity-calendars\\/55","document_id":55,"form_type":"activity_calendar","organization":"Psychology Org","status":null}	\N	2026-08-21 05:55:36	2026-08-21 05:55:36
3a4893d1-3802-4d65-80c9-0b6557c28db6	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 CODECS (1st Term 2026-2027)","body":"Activity Calendar \\u2022 CODECS","url":"\\/review\\/activity-calendars\\/52","document_id":52,"form_type":"activity_calendar","organization":"CODECS","status":null}	2026-08-24 14:45:38	2026-08-21 05:52:20	2026-08-24 14:45:38
40a753b9-ffef-4e1c-9f58-07bebc3f55c8	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 UAPSA (1st Term 2026-2027)","body":"Activity Calendar \\u2022 UAPSA","url":"\\/review\\/activity-calendars\\/53","document_id":53,"form_type":"activity_calendar","organization":"UAPSA","status":null}	2026-08-24 14:45:38	2026-08-21 05:53:29	2026-08-24 14:45:38
78c0e4c8-6da3-4df8-acb9-3044e44a7d45	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 PICE (1st Term 2026-2027)","body":"Activity Calendar \\u2022 PICE","url":"\\/review\\/activity-calendars\\/54","document_id":54,"form_type":"activity_calendar","organization":"PICE","status":null}	2026-08-24 14:45:38	2026-08-21 05:54:33	2026-08-24 14:45:38
53e24386-fe65-499f-b3cd-a12b769ef92b	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Psychology Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Psychology Org","url":"\\/review\\/activity-calendars\\/55","document_id":55,"form_type":"activity_calendar","organization":"Psychology Org","status":null}	\N	2026-08-21 05:55:47	2026-08-21 05:55:47
01307c09-5414-4b91-a92d-eec5d2065181	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 MTSC (1st Term 2026-2027)","body":"Activity Calendar \\u2022 MTSC","url":"\\/review\\/activity-calendars\\/56","document_id":56,"form_type":"activity_calendar","organization":"MTSC","status":null}	\N	2026-08-21 05:56:19	2026-08-21 05:56:19
ce5090c1-07a0-4809-a368-91ae181b4df1	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 MTSC (1st Term 2026-2027)","body":"Activity Calendar \\u2022 MTSC","url":"\\/review\\/activity-calendars\\/56","document_id":56,"form_type":"activity_calendar","organization":"MTSC","status":null}	\N	2026-08-21 05:56:29	2026-08-21 05:56:29
72c297f1-04a1-4df8-ace3-05c70fb6fe82	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	95	{"kind":"document_outcome","title":"Activity Calendar \\u2014 MTSC (1st Term 2026-2027) was approved","body":"Activity Calendar \\u2022 MTSC","url":"\\/activity-calendars\\/56","document_id":56,"form_type":"activity_calendar","organization":"MTSC","status":"approved"}	\N	2026-08-21 05:56:54	2026-08-21 05:56:54
cf7f7704-9cdf-4c9b-b78c-452d6645d65b	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 JPIA (1st Term 2026-2027)","body":"Activity Calendar \\u2022 JPIA","url":"\\/review\\/activity-calendars\\/57","document_id":57,"form_type":"activity_calendar","organization":"JPIA","status":null}	\N	2026-08-21 05:57:22	2026-08-21 05:57:22
b2b941cd-8035-4cf9-9fd5-ffd69593e30a	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 JPIA (1st Term 2026-2027)","body":"Activity Calendar \\u2022 JPIA","url":"\\/review\\/activity-calendars\\/57","document_id":57,"form_type":"activity_calendar","organization":"JPIA","status":null}	\N	2026-08-21 05:57:33	2026-08-21 05:57:33
8e3b6fca-a99c-4eea-bc5a-3c9a8de1476d	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	101	{"kind":"document_outcome","title":"Activity Calendar \\u2014 JPIA (1st Term 2026-2027) was approved","body":"Activity Calendar \\u2022 JPIA","url":"\\/activity-calendars\\/57","document_id":57,"form_type":"activity_calendar","organization":"JPIA","status":"approved"}	\N	2026-08-21 05:57:58	2026-08-21 05:57:58
443a00ae-f6f5-4177-b714-278b7223141b	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	96	{"kind":"document_outcome","title":"Activity Calendar \\u2014 JPIA (1st Term 2026-2027) was approved","body":"Activity Calendar \\u2022 JPIA","url":"\\/activity-calendars\\/57","document_id":57,"form_type":"activity_calendar","organization":"JPIA","status":"approved"}	\N	2026-08-21 05:58:02	2026-08-21 05:58:02
95617dae-e492-4043-a387-5b4e8f1f93bb	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Red Cross Youth (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Red Cross Youth","url":"\\/review\\/activity-calendars\\/58","document_id":58,"form_type":"activity_calendar","organization":"Red Cross Youth","status":null}	\N	2026-08-21 05:58:31	2026-08-21 05:58:31
b5bdffeb-dc7f-4007-a33f-3f0ddb6418a1	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Red Cross Youth (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Red Cross Youth","url":"\\/review\\/activity-calendars\\/58","document_id":58,"form_type":"activity_calendar","organization":"Red Cross Youth","status":null}	\N	2026-08-21 05:58:41	2026-08-21 05:58:41
44b2c1c6-5821-4553-bee3-2e29eada17b4	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	97	{"kind":"document_outcome","title":"Activity Calendar \\u2014 Red Cross Youth (1st Term 2026-2027) was approved","body":"Activity Calendar \\u2022 Red Cross Youth","url":"\\/activity-calendars\\/58","document_id":58,"form_type":"activity_calendar","organization":"Red Cross Youth","status":"approved"}	\N	2026-08-21 05:59:06	2026-08-21 05:59:06
e660f1f6-1608-454a-8cb9-78c38da423ee	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Venaris Esports (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Venaris Esports","url":"\\/review\\/activity-calendars\\/59","document_id":59,"form_type":"activity_calendar","organization":"Venaris Esports","status":null}	\N	2026-08-21 05:59:35	2026-08-21 05:59:35
3fd6419a-a6f7-4082-9865-8eda3409405f	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 MTSC (1st Term 2026-2027)","body":"Activity Calendar \\u2022 MTSC","url":"\\/review\\/activity-calendars\\/56","document_id":56,"form_type":"activity_calendar","organization":"MTSC","status":null}	2026-08-24 14:45:38	2026-08-21 05:56:24	2026-08-24 14:45:38
abef2a70-43ea-476e-9e84-314ac002ad65	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 JPIA (1st Term 2026-2027)","body":"Activity Calendar \\u2022 JPIA","url":"\\/review\\/activity-calendars\\/57","document_id":57,"form_type":"activity_calendar","organization":"JPIA","status":null}	2026-08-24 14:45:38	2026-08-21 05:57:28	2026-08-24 14:45:38
9b390502-596b-4f50-bea3-5787f432ed04	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Red Cross Youth (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Red Cross Youth","url":"\\/review\\/activity-calendars\\/58","document_id":58,"form_type":"activity_calendar","organization":"Red Cross Youth","status":null}	2026-08-24 14:45:38	2026-08-21 05:58:36	2026-08-24 14:45:38
195ee5d4-63bd-4be8-a5b5-27cb09439262	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Venaris Esports (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Venaris Esports","url":"\\/review\\/activity-calendars\\/59","document_id":59,"form_type":"activity_calendar","organization":"Venaris Esports","status":null}	\N	2026-08-21 05:59:45	2026-08-21 05:59:45
6c0e607e-5eeb-4dc9-bc85-e2a0ee3a5334	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	98	{"kind":"document_outcome","title":"Activity Calendar \\u2014 Venaris Esports (1st Term 2026-2027) was approved","body":"Activity Calendar \\u2022 Venaris Esports","url":"\\/activity-calendars\\/59","document_id":59,"form_type":"activity_calendar","organization":"Venaris Esports","status":"approved"}	\N	2026-08-21 06:00:10	2026-08-21 06:00:10
232822b7-a611-4f39-b9d7-9d32c9e44326	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Civil Engineering Site Visit (PICE)","body":"Activity Proposal \\u2022 PICE","url":"\\/review\\/activity-proposals\\/61","document_id":61,"form_type":"activity_proposal","organization":"PICE","status":null}	\N	2026-08-21 06:00:50	2026-08-21 06:00:50
5e6ff71e-2b3e-4785-99d2-9d28dd9eedcf	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Civil Engineering Site Visit (PICE)","body":"Activity Proposal \\u2022 PICE","url":"\\/review\\/activity-proposals\\/61","document_id":61,"form_type":"activity_proposal","organization":"PICE","status":null}	\N	2026-08-21 06:01:04	2026-08-21 06:01:04
9fb009ce-008d-472e-bf63-d310fba2c2bc	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	51	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Architecture Expo (UAPSA)","body":"Activity Proposal \\u2022 UAPSA","url":"\\/review\\/activity-proposals\\/62","document_id":62,"form_type":"activity_proposal","organization":"UAPSA","status":null}	\N	2026-08-21 06:01:37	2026-08-21 06:01:37
7c06ab96-f54b-4a55-a6ed-9b9b25272b9f	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	31	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Architecture Expo (UAPSA)","body":"Activity Proposal \\u2022 UAPSA","url":"\\/review\\/activity-proposals\\/62","document_id":62,"form_type":"activity_proposal","organization":"UAPSA","status":null}	\N	2026-08-21 06:02:00	2026-08-21 06:02:00
f0148625-526c-41e2-a803-d09e0c1d34a4	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	54	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Valorant Campus Cup (Venaris Esports)","body":"Activity Proposal \\u2022 Venaris Esports","url":"\\/review\\/activity-proposals\\/63","document_id":63,"form_type":"activity_proposal","organization":"Venaris Esports","status":null}	\N	2026-08-21 06:02:39	2026-08-21 06:02:39
0bab955e-ba46-4937-8bb0-566d49686904	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	26	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Valorant Campus Cup (Venaris Esports)","body":"Activity Proposal \\u2022 Venaris Esports","url":"\\/review\\/activity-proposals\\/63","document_id":63,"form_type":"activity_proposal","organization":"Venaris Esports","status":null}	\N	2026-08-21 06:03:06	2026-08-21 06:03:06
49ec68b5-3fe0-4e94-b63a-c17d7456ae83	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Valorant Campus Cup (Venaris Esports)","body":"Activity Proposal \\u2022 Venaris Esports","url":"\\/review\\/activity-proposals\\/63","document_id":63,"form_type":"activity_proposal","organization":"Venaris Esports","status":null}	\N	2026-08-21 06:03:35	2026-08-21 06:03:35
be5ff5ea-49b1-4e4a-8b66-a21d8cdb0693	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Valorant Campus Cup (Venaris Esports)","body":"Activity Proposal \\u2022 Venaris Esports","url":"\\/review\\/activity-proposals\\/63","document_id":63,"form_type":"activity_proposal","organization":"Venaris Esports","status":null}	\N	2026-08-21 06:03:47	2026-08-21 06:03:47
590cd2a7-403b-48be-80c6-cc055a577383	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	23	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Valorant Campus Cup (Venaris Esports)","body":"Activity Proposal \\u2022 Venaris Esports","url":"\\/review\\/activity-proposals\\/63","document_id":63,"form_type":"activity_proposal","organization":"Venaris Esports","status":null}	\N	2026-08-21 06:04:16	2026-08-21 06:04:16
9d2309d1-190a-45dd-a65d-e3b50f088e3b	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	24	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Valorant Campus Cup (Venaris Esports)","body":"Activity Proposal \\u2022 Venaris Esports","url":"\\/review\\/activity-proposals\\/63","document_id":63,"form_type":"activity_proposal","organization":"Venaris Esports","status":null}	\N	2026-08-21 06:04:38	2026-08-21 06:04:38
01e97256-0cba-457c-98ba-51b00b58eaf2	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Venaris Esports (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Venaris Esports","url":"\\/review\\/activity-calendars\\/59","document_id":59,"form_type":"activity_calendar","organization":"Venaris Esports","status":null}	2026-08-24 14:45:38	2026-08-21 05:59:40	2026-08-24 14:45:38
966eeec6-d65f-4979-88c6-b3aa9d495133	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Civil Engineering Site Visit (PICE)","body":"Activity Proposal \\u2022 PICE","url":"\\/review\\/activity-proposals\\/61","document_id":61,"form_type":"activity_proposal","organization":"PICE","status":null}	2026-08-24 14:45:38	2026-08-21 06:00:57	2026-08-24 14:45:38
24aaeacb-c069-494a-baa3-dd940f81fece	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Valorant Campus Cup (Venaris Esports)","body":"Activity Proposal \\u2022 Venaris Esports","url":"\\/review\\/activity-proposals\\/63","document_id":63,"form_type":"activity_proposal","organization":"Venaris Esports","status":null}	2026-08-24 14:45:38	2026-08-21 06:03:41	2026-08-24 14:45:38
a1b7909c-722e-4af1-ab3b-e2cd66bbbd57	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	25	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Valorant Campus Cup (Venaris Esports)","body":"Activity Proposal \\u2022 Venaris Esports","url":"\\/review\\/activity-proposals\\/63","document_id":63,"form_type":"activity_proposal","organization":"Venaris Esports","status":null}	\N	2026-08-21 06:05:00	2026-08-21 06:05:00
a8d59a19-3c96-4eda-8d03-02110dfeb122	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	98	{"kind":"document_outcome","title":"Activity Proposal \\u2014 Valorant Campus Cup (Venaris Esports) was approved","body":"Activity Proposal \\u2022 Venaris Esports","url":"\\/activity-proposals\\/63","document_id":63,"form_type":"activity_proposal","organization":"Venaris Esports","status":"approved"}	\N	2026-08-21 06:05:20	2026-08-21 06:05:20
3f785b04-b407-466d-b8e3-e18094794ed2	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 SHS Talent Night (Venaris Esports)","body":"Activity Proposal \\u2022 Venaris Esports","url":"\\/review\\/activity-proposals\\/64","document_id":64,"form_type":"activity_proposal","organization":"Venaris Esports","status":null}	\N	2026-08-21 06:05:52	2026-08-21 06:05:52
cbe1d448-6545-49d3-9776-37a3c579da41	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 SHS Talent Night (Venaris Esports)","body":"Activity Proposal \\u2022 Venaris Esports","url":"\\/review\\/activity-proposals\\/64","document_id":64,"form_type":"activity_proposal","organization":"Venaris Esports","status":null}	\N	2026-08-21 06:06:06	2026-08-21 06:06:06
b37b4d2e-fcfe-45c0-b923-11244d30c671	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	48	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Code Review Bootcamp (CODECS)","body":"Activity Proposal \\u2022 CODECS","url":"\\/review\\/activity-proposals\\/65","document_id":65,"form_type":"activity_proposal","organization":"CODECS","status":null}	\N	2026-08-21 06:06:41	2026-08-21 06:06:41
d1f3a3cc-29d3-4352-a592-b6d2f8154227	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	28	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Code Review Bootcamp (CODECS)","body":"Activity Proposal \\u2022 CODECS","url":"\\/review\\/activity-proposals\\/65","document_id":65,"form_type":"activity_proposal","organization":"CODECS","status":null}	\N	2026-08-21 06:07:08	2026-08-21 06:07:08
8c851b46-cf1f-43bd-b4d1-777b5373f7e6	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	27	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Code Review Bootcamp (CODECS)","body":"Activity Proposal \\u2022 CODECS","url":"\\/review\\/activity-proposals\\/65","document_id":65,"form_type":"activity_proposal","organization":"CODECS","status":null}	\N	2026-08-21 06:07:31	2026-08-21 06:07:31
19eb372c-838c-4605-8566-0b1b070dda1a	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Code Review Bootcamp (CODECS)","body":"Activity Proposal \\u2022 CODECS","url":"\\/review\\/activity-proposals\\/65","document_id":65,"form_type":"activity_proposal","organization":"CODECS","status":null}	\N	2026-08-21 06:08:00	2026-08-21 06:08:00
301b8aa8-1745-4be4-be45-f6231eb5b60f	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Code Review Bootcamp (CODECS)","body":"Activity Proposal \\u2022 CODECS","url":"\\/review\\/activity-proposals\\/65","document_id":65,"form_type":"activity_proposal","organization":"CODECS","status":null}	\N	2026-08-21 06:08:12	2026-08-21 06:08:12
79e337c4-0ca8-46ba-aab1-ba93c764c67c	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	23	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Code Review Bootcamp (CODECS)","body":"Activity Proposal \\u2022 CODECS","url":"\\/review\\/activity-proposals\\/65","document_id":65,"form_type":"activity_proposal","organization":"CODECS","status":null}	\N	2026-08-21 06:08:41	2026-08-21 06:08:41
db755320-4150-43f4-91d0-1d8ea270bb73	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	24	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Code Review Bootcamp (CODECS)","body":"Activity Proposal \\u2022 CODECS","url":"\\/review\\/activity-proposals\\/65","document_id":65,"form_type":"activity_proposal","organization":"CODECS","status":null}	\N	2026-08-21 06:09:03	2026-08-21 06:09:03
605c914f-535b-4662-97f0-3cfa4d838f2f	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	25	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Code Review Bootcamp (CODECS)","body":"Activity Proposal \\u2022 CODECS","url":"\\/review\\/activity-proposals\\/65","document_id":65,"form_type":"activity_proposal","organization":"CODECS","status":null}	\N	2026-08-21 06:09:25	2026-08-21 06:09:25
0ede2b79-b3dc-4bad-ad40-66a4987a2989	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	99	{"kind":"document_outcome","title":"Activity Proposal \\u2014 Code Review Bootcamp (CODECS) was approved","body":"Activity Proposal \\u2022 CODECS","url":"\\/activity-proposals\\/65","document_id":65,"form_type":"activity_proposal","organization":"CODECS","status":"approved"}	\N	2026-08-21 06:09:44	2026-08-21 06:09:44
11516805-c590-41ad-a243-987846ecb691	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	91	{"kind":"document_outcome","title":"Activity Proposal \\u2014 Code Review Bootcamp (CODECS) was approved","body":"Activity Proposal \\u2022 CODECS","url":"\\/activity-proposals\\/65","document_id":65,"form_type":"activity_proposal","organization":"CODECS","status":"approved"}	\N	2026-08-21 06:09:49	2026-08-21 06:09:49
44871738-1507-48ff-a604-c4635834426e	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 SHS Talent Night (Venaris Esports)","body":"Activity Proposal \\u2022 Venaris Esports","url":"\\/review\\/activity-proposals\\/64","document_id":64,"form_type":"activity_proposal","organization":"Venaris Esports","status":null}	2026-08-24 14:45:38	2026-08-21 06:05:59	2026-08-24 14:45:38
5963f411-2d9c-4469-9c09-db93ab4970ee	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Code Review Bootcamp (CODECS)","body":"Activity Proposal \\u2022 CODECS","url":"\\/review\\/activity-proposals\\/65","document_id":65,"form_type":"activity_proposal","organization":"CODECS","status":null}	2026-08-24 14:45:38	2026-08-21 06:08:06	2026-08-24 14:45:38
d5429125-22ba-45b6-96ee-69a667e9bcf7	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Infrastructure Career Fair (PICE)","body":"Activity Proposal \\u2022 PICE","url":"\\/review\\/activity-proposals\\/66","document_id":66,"form_type":"activity_proposal","organization":"PICE","status":null}	\N	2026-08-21 06:10:21	2026-08-21 06:10:21
d4efb5ce-8a9b-44ed-8f89-81f88b1e7775	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Infrastructure Career Fair (PICE)","body":"Activity Proposal \\u2022 PICE","url":"\\/review\\/activity-proposals\\/66","document_id":66,"form_type":"activity_proposal","organization":"PICE","status":null}	\N	2026-08-21 06:10:35	2026-08-21 06:10:35
d58c929f-c785-49d0-a837-894786c7a7de	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	85	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Infrastructure Career Fair (PICE)","body":"Activity Proposal \\u2022 PICE","url":"\\/review\\/activity-proposals\\/66","document_id":66,"form_type":"activity_proposal","organization":"PICE","status":null}	\N	2026-08-21 06:11:08	2026-08-21 06:11:08
91b627d6-eb25-49bd-9fe8-6ca743790605	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	30	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Infrastructure Career Fair (PICE)","body":"Activity Proposal \\u2022 PICE","url":"\\/review\\/activity-proposals\\/66","document_id":66,"form_type":"activity_proposal","organization":"PICE","status":null}	\N	2026-08-21 06:11:32	2026-08-21 06:11:32
4134dfc2-80b2-4c87-9eb2-8763330d4bbe	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	27	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Infrastructure Career Fair (PICE)","body":"Activity Proposal \\u2022 PICE","url":"\\/review\\/activity-proposals\\/66","document_id":66,"form_type":"activity_proposal","organization":"PICE","status":null}	\N	2026-08-21 06:11:55	2026-08-21 06:11:55
8da82061-a500-49da-ba6f-74bcc88a8fbf	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	24	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Infrastructure Career Fair (PICE)","body":"Activity Proposal \\u2022 PICE","url":"\\/review\\/activity-proposals\\/66","document_id":66,"form_type":"activity_proposal","organization":"PICE","status":null}	\N	2026-08-21 06:12:41	2026-08-21 06:12:41
16515a41-a117-4a0d-9fc7-e630ad91cd1d	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	25	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Infrastructure Career Fair (PICE)","body":"Activity Proposal \\u2022 PICE","url":"\\/review\\/activity-proposals\\/66","document_id":66,"form_type":"activity_proposal","organization":"PICE","status":null}	\N	2026-08-21 06:13:03	2026-08-21 06:13:03
23f5e9c1-fe2b-49e1-8129-c5c1f4f4ceee	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	100	{"kind":"document_outcome","title":"Activity Proposal \\u2014 Infrastructure Career Fair (PICE) was approved","body":"Activity Proposal \\u2022 PICE","url":"\\/activity-proposals\\/66","document_id":66,"form_type":"activity_proposal","organization":"PICE","status":"approved"}	\N	2026-08-21 06:13:23	2026-08-21 06:13:23
0082b24d-be19-44c5-8b88-3b67dcbd259b	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	93	{"kind":"document_outcome","title":"Activity Proposal \\u2014 Infrastructure Career Fair (PICE) was approved","body":"Activity Proposal \\u2022 PICE","url":"\\/activity-proposals\\/66","document_id":66,"form_type":"activity_proposal","organization":"PICE","status":"approved"}	\N	2026-08-21 06:13:28	2026-08-21 06:13:28
221cab98-e121-42a7-9f57-136c88fbe049	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: After-Activity Report \\u2014 Valorant Campus Cup","body":"After-Activity Report \\u2022 Venaris Esports","url":"\\/review\\/reports\\/67","document_id":67,"form_type":"after_activity_report","organization":"Venaris Esports","status":null}	\N	2026-08-21 06:14:01	2026-08-21 06:14:01
97ecf0f9-7824-4ba2-822b-492d6b41c144	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: After-Activity Report \\u2014 Valorant Campus Cup","body":"After-Activity Report \\u2022 Venaris Esports","url":"\\/review\\/reports\\/67","document_id":67,"form_type":"after_activity_report","organization":"Venaris Esports","status":null}	\N	2026-08-21 06:14:11	2026-08-21 06:14:11
f12c9a6c-eda8-4fbb-80af-9634bcb69927	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: After-Activity Report \\u2014 Code Review Bootcamp","body":"After-Activity Report \\u2022 CODECS","url":"\\/review\\/reports\\/68","document_id":68,"form_type":"after_activity_report","organization":"CODECS","status":null}	\N	2026-08-21 06:15:09	2026-08-21 06:15:09
a72f72c0-efe4-41bb-9592-61a2e2425dd0	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Infrastructure Career Fair (PICE)","body":"Activity Proposal \\u2022 PICE","url":"\\/review\\/activity-proposals\\/66","document_id":66,"form_type":"activity_proposal","organization":"PICE","status":null}	2026-08-24 14:45:38	2026-08-21 06:10:28	2026-08-24 14:45:38
70247ee9-e011-4d70-99be-3d9f04870c4c	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: After-Activity Report \\u2014 Valorant Campus Cup","body":"After-Activity Report \\u2022 Venaris Esports","url":"\\/review\\/reports\\/67","document_id":67,"form_type":"after_activity_report","organization":"Venaris Esports","status":null}	2026-08-24 14:45:38	2026-08-21 06:14:06	2026-08-24 14:45:38
6716a74b-ef43-460f-a5f4-1c79ab3211c6	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: After-Activity Report \\u2014 Code Review Bootcamp","body":"After-Activity Report \\u2022 CODECS","url":"\\/review\\/reports\\/68","document_id":68,"form_type":"after_activity_report","organization":"CODECS","status":null}	\N	2026-08-21 06:15:19	2026-08-21 06:15:19
e99c0aa7-b1ad-455b-be00-15960c1982f6	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: After-Activity Report \\u2014 Infrastructure Career Fair","body":"After-Activity Report \\u2022 PICE","url":"\\/review\\/reports\\/69","document_id":69,"form_type":"after_activity_report","organization":"PICE","status":null}	\N	2026-08-21 06:15:57	2026-08-21 06:15:57
a50dc9e8-0943-49c9-bf7b-1278f3c38dec	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: After-Activity Report \\u2014 Infrastructure Career Fair","body":"After-Activity Report \\u2022 PICE","url":"\\/review\\/reports\\/69","document_id":69,"form_type":"after_activity_report","organization":"PICE","status":null}	\N	2026-08-21 06:16:07	2026-08-21 06:16:07
c7713438-5e05-4c0d-a732-7d46061c6dc3	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	100	{"kind":"document_outcome","title":"After-Activity Report \\u2014 Infrastructure Career Fair was rejected","body":"After-Activity Report \\u2022 PICE","url":"\\/reports\\/69","document_id":69,"form_type":"after_activity_report","organization":"PICE","status":"rejected"}	\N	2026-08-21 06:16:18	2026-08-21 06:16:18
204c49d2-63ca-4fb3-bf51-79dba786e2d9	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	93	{"kind":"document_outcome","title":"After-Activity Report \\u2014 Infrastructure Career Fair was rejected","body":"After-Activity Report \\u2022 PICE","url":"\\/reports\\/69","document_id":69,"form_type":"after_activity_report","organization":"PICE","status":"rejected"}	\N	2026-08-21 06:16:22	2026-08-21 06:16:22
eaa8d3f6-95bb-4eb2-bbc5-67758f9e8c54	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 UAPSA (1st Term 2026-2027)","body":"Activity Calendar \\u2022 UAPSA","url":"\\/review\\/activity-calendars\\/53","document_id":53,"form_type":"activity_calendar","organization":"UAPSA","status":null}	2026-08-21 06:16:29	2026-08-21 05:53:19	2026-08-21 06:16:29
24632a9f-4384-4178-a629-922e8a737bcf	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 PICE (1st Term 2026-2027)","body":"Activity Calendar \\u2022 PICE","url":"\\/review\\/activity-calendars\\/54","document_id":54,"form_type":"activity_calendar","organization":"PICE","status":null}	2026-08-21 06:16:29	2026-08-21 05:54:23	2026-08-21 06:16:29
88faea31-9f99-4e19-b34a-855c9478326e	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Psychology Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Psychology Org","url":"\\/review\\/activity-calendars\\/55","document_id":55,"form_type":"activity_calendar","organization":"Psychology Org","status":null}	2026-08-21 06:16:29	2026-08-21 05:55:31	2026-08-21 06:16:29
7b113a0e-e3eb-4bde-bfb7-0555911ba492	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 MTSC (1st Term 2026-2027)","body":"Activity Calendar \\u2022 MTSC","url":"\\/review\\/activity-calendars\\/56","document_id":56,"form_type":"activity_calendar","organization":"MTSC","status":null}	2026-08-21 06:16:29	2026-08-21 05:56:13	2026-08-21 06:16:29
8466ecf1-e06d-40f0-aea0-9f4adcf26059	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 JPIA (1st Term 2026-2027)","body":"Activity Calendar \\u2022 JPIA","url":"\\/review\\/activity-calendars\\/57","document_id":57,"form_type":"activity_calendar","organization":"JPIA","status":null}	2026-08-21 06:16:29	2026-08-21 05:57:17	2026-08-21 06:16:29
718f5d47-1642-4e02-adb6-168f5b73f74c	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Red Cross Youth (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Red Cross Youth","url":"\\/review\\/activity-calendars\\/58","document_id":58,"form_type":"activity_calendar","organization":"Red Cross Youth","status":null}	2026-08-21 06:16:29	2026-08-21 05:58:25	2026-08-21 06:16:29
f01382f3-80e6-45b7-a27e-35cb4cf49c1b	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Venaris Esports (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Venaris Esports","url":"\\/review\\/activity-calendars\\/59","document_id":59,"form_type":"activity_calendar","organization":"Venaris Esports","status":null}	2026-08-21 06:16:29	2026-08-21 05:59:29	2026-08-21 06:16:29
d7dc25c0-aad9-4b09-b9fd-ec76e3cfad60	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Civil Engineering Site Visit (PICE)","body":"Activity Proposal \\u2022 PICE","url":"\\/review\\/activity-proposals\\/61","document_id":61,"form_type":"activity_proposal","organization":"PICE","status":null}	2026-08-21 06:16:29	2026-08-21 06:00:43	2026-08-21 06:16:29
5701cbb9-fa5c-483e-b951-db3f76756821	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Valorant Campus Cup (Venaris Esports)","body":"Activity Proposal \\u2022 Venaris Esports","url":"\\/review\\/activity-proposals\\/63","document_id":63,"form_type":"activity_proposal","organization":"Venaris Esports","status":null}	2026-08-21 06:16:29	2026-08-21 06:03:29	2026-08-21 06:16:29
20547f8e-c622-4263-bd33-cc8bde9dadbf	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 SHS Talent Night (Venaris Esports)","body":"Activity Proposal \\u2022 Venaris Esports","url":"\\/review\\/activity-proposals\\/64","document_id":64,"form_type":"activity_proposal","organization":"Venaris Esports","status":null}	2026-08-21 06:16:29	2026-08-21 06:05:45	2026-08-21 06:16:29
04b4378f-0fc6-4d3e-a2a2-c3137aca01c6	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Code Review Bootcamp (CODECS)","body":"Activity Proposal \\u2022 CODECS","url":"\\/review\\/activity-proposals\\/65","document_id":65,"form_type":"activity_proposal","organization":"CODECS","status":null}	2026-08-21 06:16:29	2026-08-21 06:07:54	2026-08-21 06:16:29
b94850aa-5b89-4d3c-a176-f209187f29b9	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: After-Activity Report \\u2014 Code Review Bootcamp","body":"After-Activity Report \\u2022 CODECS","url":"\\/review\\/reports\\/68","document_id":68,"form_type":"after_activity_report","organization":"CODECS","status":null}	2026-08-24 14:45:38	2026-08-21 06:15:14	2026-08-24 14:45:38
6ab6633e-a6a9-4b42-9f31-d39145ecd319	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Infrastructure Career Fair (PICE)","body":"Activity Proposal \\u2022 PICE","url":"\\/review\\/activity-proposals\\/66","document_id":66,"form_type":"activity_proposal","organization":"PICE","status":null}	2026-08-21 06:16:29	2026-08-21 06:10:15	2026-08-21 06:16:29
bdeff761-0373-4a8c-bd1b-1055356cdcfd	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: After-Activity Report \\u2014 Valorant Campus Cup","body":"After-Activity Report \\u2022 Venaris Esports","url":"\\/review\\/reports\\/67","document_id":67,"form_type":"after_activity_report","organization":"Venaris Esports","status":null}	2026-08-21 06:16:29	2026-08-21 06:13:55	2026-08-21 06:16:29
ae3bbda9-5f0d-4cff-adba-823fc0dbaa08	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: After-Activity Report \\u2014 Code Review Bootcamp","body":"After-Activity Report \\u2022 CODECS","url":"\\/review\\/reports\\/68","document_id":68,"form_type":"after_activity_report","organization":"CODECS","status":null}	2026-08-21 06:16:29	2026-08-21 06:15:04	2026-08-21 06:16:29
31b8c26a-3c8e-4a6a-93c1-8e6e569e6d65	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: After-Activity Report \\u2014 Infrastructure Career Fair","body":"After-Activity Report \\u2022 PICE","url":"\\/review\\/reports\\/69","document_id":69,"form_type":"after_activity_report","organization":"PICE","status":null}	2026-08-21 06:16:29	2026-08-21 06:15:52	2026-08-21 06:16:29
5fc79ab3-4ce9-40e8-b23a-7f96e7ace1e2	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	23	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Infrastructure Career Fair (PICE)","body":"Activity Proposal \\u2022 PICE","url":"\\/review\\/activity-proposals\\/66","document_id":66,"form_type":"activity_proposal","organization":"PICE","status":null}	2026-08-21 06:16:31	2026-08-21 06:12:19	2026-08-21 06:16:31
fdd5fd27-e600-45e9-8313-125842cf715f	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	92	{"kind":"document_outcome","title":"Activity Proposal \\u2014 Architecture Expo (UAPSA) was returned for revision","body":"Activity Proposal \\u2022 UAPSA","url":"\\/activity-proposals\\/62","document_id":62,"form_type":"activity_proposal","organization":"UAPSA","status":"returned"}	2026-08-21 06:16:34	2026-08-21 06:02:16	2026-08-21 06:16:34
f7dada2a-c1f1-4c5d-b350-ef30587aadb7	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	98	{"kind":"document_outcome","title":"Activity Proposal \\u2014 SHS Talent Night (Venaris Esports) was rejected","body":"Activity Proposal \\u2022 Venaris Esports","url":"\\/activity-proposals\\/64","document_id":64,"form_type":"activity_proposal","organization":"Venaris Esports","status":"rejected"}	2026-08-21 06:16:36	2026-08-21 06:06:19	2026-08-21 06:16:36
8b77f025-af06-46f8-a629-98a59ca2be0b	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	98	{"kind":"document_outcome","title":"After-Activity Report \\u2014 Valorant Campus Cup was approved","body":"After-Activity Report \\u2022 Venaris Esports","url":"\\/reports\\/67","document_id":67,"form_type":"after_activity_report","organization":"Venaris Esports","status":"approved"}	2026-08-21 06:16:36	2026-08-21 06:14:37	2026-08-21 06:16:36
22d35e0d-c602-4e76-881b-0c6558c814e2	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 JPIA (1st Term 2026-2027)","body":"Activity Calendar \\u2022 JPIA","url":"\\/review\\/activity-calendars\\/70","document_id":70,"form_type":"activity_calendar","organization":"JPIA","status":null}	\N	2026-08-23 12:53:47	2026-08-23 12:53:47
c5981ded-1b51-4463-be4c-8588a32d515d	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 JPIA (1st Term 2026-2027)","body":"Activity Calendar \\u2022 JPIA","url":"\\/review\\/activity-calendars\\/70","document_id":70,"form_type":"activity_calendar","organization":"JPIA","status":null}	\N	2026-08-23 12:53:49	2026-08-23 12:53:49
a7e398ca-ad75-42b0-b83c-bc039ff58225	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 JPIA (1st Term 2026-2027)","body":"Activity Calendar \\u2022 JPIA","url":"\\/review\\/activity-calendars\\/70","document_id":70,"form_type":"activity_calendar","organization":"JPIA","status":null}	\N	2026-08-23 12:53:55	2026-08-23 12:53:55
63097437-b902-4fe3-b166-35f5ad5fb394	App\\Notifications\\AccountVerifiedNotification	App\\Models\\User	114	{"kind":"account_verified","title":"Your SDAO account has been verified","body":"You can now submit documents and be bound to an organization.","url":"\\/dashboard","document_id":null,"form_type":null,"organization":null,"status":null}	\N	2026-08-24 02:14:50	2026-08-24 02:14:50
53646657-a3c6-4bef-9e42-4f6e689ff732	App\\Notifications\\JoinRequestReceivedNotification	App\\Models\\User	99	{"kind":"join_request_received","title":"Join request: Ranuel Glenn Viray wants to join CODECS","body":"Requesting to join \\u2022 CODECS","url":"\\/review\\/join-requests","document_id":null,"form_type":null,"organization":"CODECS","status":null}	\N	2026-08-24 02:50:59	2026-08-24 02:50:59
486807ed-190a-48e2-bd12-be8ad976db10	App\\Notifications\\JoinRequestReceivedNotification	App\\Models\\User	91	{"kind":"join_request_received","title":"Join request: Ranuel Glenn Viray wants to join CODECS","body":"Requesting to join \\u2022 CODECS","url":"\\/review\\/join-requests","document_id":null,"form_type":null,"organization":"CODECS","status":null}	\N	2026-08-24 02:51:00	2026-08-24 02:51:00
20f52b90-314e-4b96-a127-84ad4ac56160	App\\Notifications\\JoinRequestReceivedNotification	App\\Models\\User	48	{"kind":"join_request_received","title":"Join request: Ranuel Glenn Viray wants to join CODECS","body":"Requesting to join \\u2022 CODECS","url":"\\/review\\/join-requests","document_id":null,"form_type":null,"organization":"CODECS","status":null}	\N	2026-08-24 02:51:02	2026-08-24 02:51:02
e5412103-570f-4875-878e-2fb859c3f81c	App\\Notifications\\JoinRequestReceivedNotification	App\\Models\\User	99	{"kind":"join_request_received","title":"Join request: Alcantara, Kristian Diether A. wants to join CODECS","body":"Requesting to join \\u2022 CODECS","url":"\\/review\\/join-requests","document_id":null,"form_type":null,"organization":"CODECS","status":null}	\N	2026-08-24 04:33:32	2026-08-24 04:33:32
c1eeebc9-49bf-441c-8895-cb48bf525682	App\\Notifications\\JoinRequestReceivedNotification	App\\Models\\User	91	{"kind":"join_request_received","title":"Join request: Alcantara, Kristian Diether A. wants to join CODECS","body":"Requesting to join \\u2022 CODECS","url":"\\/review\\/join-requests","document_id":null,"form_type":null,"organization":"CODECS","status":null}	\N	2026-08-24 04:33:34	2026-08-24 04:33:34
080de42a-8a24-4291-8dcf-e3f57ef40121	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 JPIA (1st Term 2026-2027)","body":"Activity Calendar \\u2022 JPIA","url":"\\/review\\/activity-calendars\\/70","document_id":70,"form_type":"activity_calendar","organization":"JPIA","status":null}	2026-08-24 14:45:38	2026-08-23 12:53:52	2026-08-24 14:45:38
f5228df3-bc3c-4fce-9d43-0806e3906e10	App\\Notifications\\JoinRequestDeclinedNotification	App\\Models\\User	114	{"kind":"join_request_declined","title":"Request declined \\u2014 CODECS","body":"Your request to join was not approved this time.","url":"\\/dashboard","document_id":null,"form_type":null,"organization":"CODECS","status":"declined"}	\N	2026-08-24 04:34:01	2026-08-24 04:34:01
db18cb65-9113-4d07-9291-51d851165302	App\\Notifications\\JoinRequestReceivedNotification	App\\Models\\User	48	{"kind":"join_request_received","title":"Join request: Alcantara, Kristian Diether A. wants to join CODECS","body":"Requesting to join \\u2022 CODECS","url":"\\/review\\/join-requests","document_id":null,"form_type":null,"organization":"CODECS","status":null}	2026-08-24 04:50:10	2026-08-24 04:33:35	2026-08-24 04:50:10
ad3e0c0f-c034-4612-8141-6c1c6d832516	App\\Notifications\\JoinRequestDeclinedNotification	App\\Models\\User	117	{"kind":"join_request_declined","title":"Request declined \\u2014 CODECS","body":"Your request to join was not approved this time.","url":"\\/dashboard","document_id":null,"form_type":null,"organization":"CODECS","status":"declined"}	\N	2026-08-24 04:50:53	2026-08-24 04:50:53
7bc3823e-e03c-4f5f-a523-c08ab1974724	App\\Notifications\\AccountRejectedNotification	App\\Models\\User	118	{"kind":"account_rejected","title":"Your SDAO account application was not approved","body":"Contact SDAO if you believe this was a mistake.","url":"\\/dashboard","document_id":null,"form_type":null,"organization":null,"status":null}	\N	2026-08-24 04:59:26	2026-08-24 04:59:26
b493a967-769d-434b-863c-f28fb8fcd45a	App\\Notifications\\JoinRequestReceivedNotification	App\\Models\\User	95	{"kind":"join_request_received","title":"Join request: Alcantara, Kristian Diether A. wants to join MTSC","body":"Requesting to join \\u2022 MTSC","url":"\\/review\\/join-requests","document_id":null,"form_type":null,"organization":"MTSC","status":null}	\N	2026-08-24 05:13:04	2026-08-24 05:13:04
b0a3c0ca-ff57-4aed-94a1-58c95a606550	App\\Notifications\\JoinRequestReceivedNotification	App\\Models\\User	87	{"kind":"join_request_received","title":"Join request: Alcantara, Kristian Diether A. wants to join MTSC","body":"Requesting to join \\u2022 MTSC","url":"\\/review\\/join-requests","document_id":null,"form_type":null,"organization":"MTSC","status":null}	2026-08-24 05:16:53	2026-08-24 05:13:06	2026-08-24 05:16:53
240fa897-d3fd-4e0b-85ea-6de281100bc7	App\\Notifications\\AccountVerifiedNotification	App\\Models\\User	120	{"kind":"account_verified","title":"Your SDAO account has been verified","body":"You can now submit documents and be bound to an organization.","url":"\\/dashboard","document_id":null,"form_type":null,"organization":null,"status":null}	\N	2026-08-24 05:27:27	2026-08-24 05:27:27
759d2d2c-7a98-4bdd-8d23-353152fc9a0e	App\\Notifications\\AccountVerifiedNotification	App\\Models\\User	121	{"kind":"account_verified","title":"Your SDAO account has been verified","body":"You can now submit documents and be bound to an organization.","url":"\\/dashboard","document_id":null,"form_type":null,"organization":null,"status":null}	2026-08-24 05:34:37	2026-08-24 05:18:49	2026-08-24 05:34:37
992d5b6e-3285-42c9-9830-d83c81b55862	App\\Notifications\\JoinRequestApprovedNotification	App\\Models\\User	120	{"kind":"join_request_approved","title":"You're in \\u2014 MTSC","body":"Your request to join was approved. You now have officer access.","url":"\\/dashboard","document_id":null,"form_type":null,"organization":"MTSC","status":"approved"}	\N	2026-08-24 05:40:08	2026-08-24 05:40:08
bb52966f-6ce9-4364-85f4-a0a2d9578290	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Cosplayers (2026-2027)","body":"Organization Registration \\u2022 Cosplayers","url":"\\/review\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":null}	\N	2026-08-24 05:50:32	2026-08-24 05:50:32
bff50829-0e92-49ed-969c-575db490b34e	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Cosplayers (2026-2027)","body":"Organization Registration \\u2022 Cosplayers","url":"\\/review\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":null}	\N	2026-08-24 05:50:34	2026-08-24 05:50:34
8c00fd0f-2243-4196-b8e6-07a89aeff522	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Cosplayers (2026-2027)","body":"Organization Registration \\u2022 Cosplayers","url":"\\/review\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":null}	\N	2026-08-24 05:50:37	2026-08-24 05:50:37
19993e97-4c8d-41d3-81eb-6834033b281d	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Cosplayers (2026-2027)","body":"Organization Registration \\u2022 Cosplayers","url":"\\/review\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":null}	\N	2026-08-24 05:57:06	2026-08-24 05:57:06
1f5ec796-ddb7-4b55-8abe-c2eedb05c648	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Cosplayers (2026-2027)","body":"Organization Registration \\u2022 Cosplayers","url":"\\/review\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":null}	\N	2026-08-24 05:57:11	2026-08-24 05:57:11
433db2ab-e41c-4001-8374-70528cae896d	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Cosplayers (2026-2027)","body":"Organization Registration \\u2022 Cosplayers","url":"\\/review\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":null}	2026-08-24 06:02:22	2026-08-24 05:57:08	2026-08-24 06:02:22
75dadebc-444e-45a9-b459-585ccdf7cc88	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Cosplayers (2026-2027)","body":"Organization Registration \\u2022 Cosplayers","url":"\\/review\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":null}	\N	2026-08-24 06:03:41	2026-08-24 06:03:41
1453f184-a58c-4fa3-bc87-802f51f863f2	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Cosplayers (2026-2027)","body":"Organization Registration \\u2022 Cosplayers","url":"\\/review\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":null}	2026-08-24 14:45:38	2026-08-24 05:50:36	2026-08-24 14:45:38
5b7c07dc-cf7c-4fce-a952-33c68481c94c	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	121	{"kind":"document_outcome","title":"Organization Registration \\u2014 Cosplayers (2026-2027) was returned for revision","body":"Organization Registration \\u2022 Cosplayers","url":"\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":"returned"}	2026-08-24 05:59:28	2026-08-24 05:59:06	2026-08-24 05:59:28
53a2bdf8-0e0a-4b24-96cf-8f80b052e32c	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Cosplayers (2026-2027)","body":"Organization Registration \\u2022 Cosplayers","url":"\\/review\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":null}	\N	2026-08-24 06:16:20	2026-08-24 06:16:20
3d6b058e-36ed-47e2-a342-1f32a2c33e4f	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Cosplayers (2026-2027)","body":"Organization Registration \\u2022 Cosplayers","url":"\\/review\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":null}	\N	2026-08-24 06:16:22	2026-08-24 06:16:22
e2e80aa7-19ac-4e23-879f-5d884631ffe3	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Cosplayers (2026-2027)","body":"Organization Registration \\u2022 Cosplayers","url":"\\/review\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":null}	\N	2026-08-24 06:16:26	2026-08-24 06:16:26
360b8d05-0ef6-4caf-a8b7-eb931388d93b	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Cosplayers (2026-2027)","body":"Organization Registration \\u2022 Cosplayers","url":"\\/review\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":null}	2026-08-24 14:45:38	2026-08-24 06:16:24	2026-08-24 14:45:38
96cc6b43-7d45-4f89-9384-b1ffaf13b449	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Cosplayers (2026-2027)","body":"Organization Registration \\u2022 Cosplayers","url":"\\/review\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":null}	\N	2026-08-24 06:03:43	2026-08-24 06:03:43
fe93f954-adec-4d10-be08-2d382554239d	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Cosplayers (2026-2027)","body":"Organization Registration \\u2022 Cosplayers","url":"\\/review\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":null}	\N	2026-08-24 06:03:46	2026-08-24 06:03:46
04663a9e-b90a-45ab-ba22-a0a491476f3e	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Cosplayers (2026-2027)","body":"Organization Registration \\u2022 Cosplayers","url":"\\/review\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":null}	2026-08-24 14:45:38	2026-08-24 06:03:44	2026-08-24 14:45:38
f2bc65cf-35ea-4c27-9f91-e8e6308037e1	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Cosplayers (2026-2027)","body":"Organization Registration \\u2022 Cosplayers","url":"\\/review\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":null}	\N	2026-08-24 06:12:30	2026-08-24 06:12:30
316e426e-5f8b-4dd9-8018-93f735758032	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Cosplayers (2026-2027)","body":"Organization Registration \\u2022 Cosplayers","url":"\\/review\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":null}	\N	2026-08-24 06:12:32	2026-08-24 06:12:32
55cb525e-7e8c-4fc1-8f56-58f9ff9533c4	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Cosplayers (2026-2027)","body":"Organization Registration \\u2022 Cosplayers","url":"\\/review\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":null}	\N	2026-08-24 06:12:35	2026-08-24 06:12:35
c5ea1d5f-a07f-4b07-a200-2cc677644e3e	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	121	{"kind":"document_outcome","title":"Organization Registration \\u2014 Cosplayers (2026-2027) was returned for revision","body":"Organization Registration \\u2022 Cosplayers","url":"\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":"returned"}	2026-08-24 06:17:56	2026-08-24 06:14:12	2026-08-24 06:17:56
2b17082b-1544-4102-991a-187cb45698f8	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	121	{"kind":"document_outcome","title":"Organization Registration \\u2014 Cosplayers (2026-2027) was returned for revision","body":"Organization Registration \\u2022 Cosplayers","url":"\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":"returned"}	2026-08-24 06:23:47	2026-08-24 06:11:46	2026-08-24 06:23:47
fd2c41be-9bd2-4697-a35d-29ed978a2ded	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 Red Cross Youth (2026-2027)","body":"Organization Renewal \\u2022 Red Cross Youth","url":"\\/review\\/renewals\\/72","document_id":72,"form_type":"organization_renewal","organization":"Red Cross Youth","status":null}	\N	2026-08-24 06:29:59	2026-08-24 06:29:59
273d8358-a72f-4de6-9e82-a4a4fcc6b1a6	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 Red Cross Youth (2026-2027)","body":"Organization Renewal \\u2022 Red Cross Youth","url":"\\/review\\/renewals\\/72","document_id":72,"form_type":"organization_renewal","organization":"Red Cross Youth","status":null}	\N	2026-08-24 06:30:01	2026-08-24 06:30:01
40753edc-18ee-482c-8ff4-2b80b2c3776e	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 Red Cross Youth (2026-2027)","body":"Organization Renewal \\u2022 Red Cross Youth","url":"\\/review\\/renewals\\/72","document_id":72,"form_type":"organization_renewal","organization":"Red Cross Youth","status":null}	\N	2026-08-24 06:30:04	2026-08-24 06:30:04
dbd2d5bc-3d4b-4229-95b7-bbb96aecf61e	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	97	{"kind":"document_outcome","title":"Organization Renewal \\u2014 Red Cross Youth (2026-2027) was approved","body":"Organization Renewal \\u2022 Red Cross Youth","url":"\\/renewals\\/72","document_id":72,"form_type":"organization_renewal","organization":"Red Cross Youth","status":"approved"}	2026-08-24 06:38:48	2026-08-24 06:38:09	2026-08-24 06:38:48
da3bde24-0114-420b-bbf7-b6dc3d56c71e	App\\Notifications\\AccountRejectedNotification	App\\Models\\User	124	{"kind":"account_rejected","title":"Your SDAO account application was not approved","body":"Contact SDAO if you believe this was a mistake.","url":"\\/dashboard","document_id":null,"form_type":null,"organization":null,"status":null}	\N	2026-08-24 07:49:26	2026-08-24 07:49:26
10b0d0c8-e5d9-40de-822b-a23492aaf074	App\\Notifications\\AccountVerifiedNotification	App\\Models\\User	125	{"kind":"account_verified","title":"Your SDAO account has been verified","body":"You can now submit documents and be bound to an organization.","url":"\\/dashboard","document_id":null,"form_type":null,"organization":null,"status":null}	\N	2026-08-24 08:26:05	2026-08-24 08:26:05
640f4840-4164-48c7-a290-92eff91b71cd	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Valorant Club (2026-2027)","body":"Organization Registration \\u2022 Valorant Club","url":"\\/review\\/registrations\\/73","document_id":73,"form_type":"organization_registration","organization":"Valorant Club","status":null}	\N	2026-08-24 08:49:06	2026-08-24 08:49:06
8b07c0c9-4e36-497f-aa3a-4f1aa1194dcb	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Valorant Club (2026-2027)","body":"Organization Registration \\u2022 Valorant Club","url":"\\/review\\/registrations\\/73","document_id":73,"form_type":"organization_registration","organization":"Valorant Club","status":null}	\N	2026-08-24 08:49:08	2026-08-24 08:49:08
4543abc2-946d-4148-9b56-1ac38205b859	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Valorant Club (2026-2027)","body":"Organization Registration \\u2022 Valorant Club","url":"\\/review\\/registrations\\/73","document_id":73,"form_type":"organization_registration","organization":"Valorant Club","status":null}	\N	2026-08-24 08:49:11	2026-08-24 08:49:11
b2bb7b33-0703-4bbe-bd15-d83bd36f457e	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Cosplayers (2026-2027)","body":"Organization Registration \\u2022 Cosplayers","url":"\\/review\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":null}	2026-08-24 14:45:38	2026-08-24 06:12:34	2026-08-24 14:45:38
737027d6-d68d-473b-a3e8-a0d38cf623a4	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Valorant Club (2026-2027)","body":"Organization Registration \\u2022 Valorant Club","url":"\\/review\\/registrations\\/74","document_id":74,"form_type":"organization_registration","organization":"Valorant Club","status":null}	\N	2026-08-24 09:12:15	2026-08-24 09:12:15
539fc655-9efc-414d-8d71-7985869f7d8f	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Valorant Club (2026-2027)","body":"Organization Registration \\u2022 Valorant Club","url":"\\/review\\/registrations\\/74","document_id":74,"form_type":"organization_registration","organization":"Valorant Club","status":null}	\N	2026-08-24 09:12:17	2026-08-24 09:12:17
d0681808-5f17-464b-9837-98a5c9ffd589	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Valorant Club (2026-2027)","body":"Organization Registration \\u2022 Valorant Club","url":"\\/review\\/registrations\\/74","document_id":74,"form_type":"organization_registration","organization":"Valorant Club","status":null}	\N	2026-08-24 09:12:20	2026-08-24 09:12:20
e09e4448-61b4-4ad1-9142-9cd9cd96d8e4	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Valorant Club (2026-2027)","body":"Organization Registration \\u2022 Valorant Club","url":"\\/review\\/registrations\\/74","document_id":74,"form_type":"organization_registration","organization":"Valorant Club","status":null}	\N	2026-08-24 09:17:39	2026-08-24 09:17:39
40044898-42f0-4634-8167-d40dad2f2813	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Valorant Club (2026-2027)","body":"Organization Registration \\u2022 Valorant Club","url":"\\/review\\/registrations\\/74","document_id":74,"form_type":"organization_registration","organization":"Valorant Club","status":null}	\N	2026-08-24 09:17:41	2026-08-24 09:17:41
08195ffe-0638-4fb2-8eb3-ebc187686d69	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Valorant Club (2026-2027)","body":"Organization Registration \\u2022 Valorant Club","url":"\\/review\\/registrations\\/74","document_id":74,"form_type":"organization_registration","organization":"Valorant Club","status":null}	\N	2026-08-24 09:17:45	2026-08-24 09:17:45
94dae2c2-63d7-4d78-b16f-56223b6d3d1c	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Valorant Club (2026-2027)","body":"Organization Registration \\u2022 Valorant Club","url":"\\/review\\/registrations\\/74","document_id":74,"form_type":"organization_registration","organization":"Valorant Club","status":null}	\N	2026-08-24 09:27:58	2026-08-24 09:27:58
55e530e5-dd9b-4aba-baf3-9e09ac99f6f6	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Valorant Club (2026-2027)","body":"Organization Registration \\u2022 Valorant Club","url":"\\/review\\/registrations\\/74","document_id":74,"form_type":"organization_registration","organization":"Valorant Club","status":null}	\N	2026-08-24 09:27:59	2026-08-24 09:27:59
36446c42-4ae7-4a4c-942b-40ae2f99f3df	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Valorant Club (2026-2027)","body":"Organization Registration \\u2022 Valorant Club","url":"\\/review\\/registrations\\/74","document_id":74,"form_type":"organization_registration","organization":"Valorant Club","status":null}	\N	2026-08-24 09:28:03	2026-08-24 09:28:03
3971b854-363b-476b-9796-4af360032c1d	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Cosplayers (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Cosplayers","url":"\\/review\\/activity-calendars\\/75","document_id":75,"form_type":"activity_calendar","organization":"Cosplayers","status":null}	\N	2026-08-24 12:25:57	2026-08-24 12:25:57
75b410d7-5bfe-4604-a844-6d3186225bd7	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Cosplayers (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Cosplayers","url":"\\/review\\/activity-calendars\\/75","document_id":75,"form_type":"activity_calendar","organization":"Cosplayers","status":null}	\N	2026-08-24 12:25:59	2026-08-24 12:25:59
eb845c21-09bf-4d5a-87c9-1cef24df3a25	App\\Notifications\\AccountVerifiedNotification	App\\Models\\User	127	{"kind":"account_verified","title":"Your SDAO account has been verified","body":"You can now submit documents and be bound to an organization.","url":"\\/dashboard","document_id":null,"form_type":null,"organization":null,"status":null}	2026-08-29 14:02:37	2026-08-24 09:05:40	2026-08-29 14:02:37
72c685af-1ed4-4580-a5ae-792306a1df22	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	127	{"kind":"document_outcome","title":"Organization Registration \\u2014 Valorant Club (2026-2027) was returned for revision","body":"Organization Registration \\u2022 Valorant Club","url":"\\/registrations\\/74","document_id":74,"form_type":"organization_registration","organization":"Valorant Club","status":"returned"}	2026-08-29 14:02:37	2026-08-24 09:15:59	2026-08-29 14:02:37
97203fa1-78e4-46d7-af61-957bec0f042a	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	127	{"kind":"document_outcome","title":"Organization Registration \\u2014 Valorant Club (2026-2027) was returned for revision","body":"Organization Registration \\u2022 Valorant Club","url":"\\/registrations\\/74","document_id":74,"form_type":"organization_registration","organization":"Valorant Club","status":"returned"}	2026-08-29 14:02:37	2026-08-24 09:25:58	2026-08-29 14:02:37
ae62a3fc-a5a6-412a-ab44-72442f3a097c	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Cosplayers (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Cosplayers","url":"\\/review\\/activity-calendars\\/75","document_id":75,"form_type":"activity_calendar","organization":"Cosplayers","status":null}	\N	2026-08-24 12:26:04	2026-08-24 12:26:04
011ae40b-bc1b-4530-bb0a-124adc9552ae	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Cosplayers (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Cosplayers","url":"\\/review\\/activity-calendars\\/76","document_id":76,"form_type":"activity_calendar","organization":"Cosplayers","status":null}	\N	2026-08-24 12:42:42	2026-08-24 12:42:42
de4588d0-7ec8-42c0-b63a-89c2831bad73	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Cosplayers (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Cosplayers","url":"\\/review\\/activity-calendars\\/76","document_id":76,"form_type":"activity_calendar","organization":"Cosplayers","status":null}	\N	2026-08-24 12:42:44	2026-08-24 12:42:44
0a21529e-bdc1-43f5-9d5e-c1f97f63e0f7	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Cosplayers (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Cosplayers","url":"\\/review\\/activity-calendars\\/76","document_id":76,"form_type":"activity_calendar","organization":"Cosplayers","status":null}	\N	2026-08-24 12:42:47	2026-08-24 12:42:47
67ddc3b5-1137-4081-9588-a5a2bf763f96	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	121	{"kind":"document_outcome","title":"Activity Calendar \\u2014 Cosplayers (1st Term 2026-2027) was returned for revision","body":"Activity Calendar \\u2022 Cosplayers","url":"\\/activity-calendars\\/76","document_id":76,"form_type":"activity_calendar","organization":"Cosplayers","status":"returned"}	2026-08-24 12:50:02	2026-08-24 12:48:22	2026-08-24 12:50:02
e7bf75c4-5046-4d5e-a982-0a78ca7fe24f	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Resubmitted for your review: Activity Calendar \\u2014 Cosplayers (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Cosplayers","url":"\\/review\\/activity-calendars\\/76","document_id":76,"form_type":"activity_calendar","organization":"Cosplayers","status":null}	\N	2026-08-24 12:50:35	2026-08-24 12:50:35
d1edab6b-46f8-49e4-a599-1dc5b7c94a50	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Resubmitted for your review: Activity Calendar \\u2014 Cosplayers (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Cosplayers","url":"\\/review\\/activity-calendars\\/76","document_id":76,"form_type":"activity_calendar","organization":"Cosplayers","status":null}	\N	2026-08-24 12:50:37	2026-08-24 12:50:37
a8fa36c7-fa88-48ee-800c-cf86e9782caf	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Resubmitted for your review: Activity Calendar \\u2014 Cosplayers (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Cosplayers","url":"\\/review\\/activity-calendars\\/76","document_id":76,"form_type":"activity_calendar","organization":"Cosplayers","status":null}	\N	2026-08-24 12:50:41	2026-08-24 12:50:41
98893c4a-922a-4290-b9b8-df4c9811da31	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	122	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Pakain Program (Cosplayers)","body":"Activity Proposal \\u2022 Cosplayers","url":"\\/review\\/activity-proposals\\/78","document_id":78,"form_type":"activity_proposal","organization":"Cosplayers","status":null}	\N	2026-08-24 13:06:39	2026-08-24 13:06:39
e6d4d2c9-5661-4d7b-a79f-5d327774ec4c	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Student Leadership Workshop (Cosplayers)","body":"Activity Proposal \\u2022 Cosplayers","url":"\\/review\\/activity-proposals\\/79","document_id":79,"form_type":"activity_proposal","organization":"Cosplayers","status":null}	\N	2026-08-24 13:55:11	2026-08-24 13:55:11
e262740b-1088-4034-ad4f-763c04b53198	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Student Leadership Workshop (Cosplayers)","body":"Activity Proposal \\u2022 Cosplayers","url":"\\/review\\/activity-proposals\\/79","document_id":79,"form_type":"activity_proposal","organization":"Cosplayers","status":null}	\N	2026-08-24 13:55:13	2026-08-24 13:55:13
5a013265-62e8-4bdc-bdd0-998b22c29319	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Student Leadership Workshop (Cosplayers)","body":"Activity Proposal \\u2022 Cosplayers","url":"\\/review\\/activity-proposals\\/79","document_id":79,"form_type":"activity_proposal","organization":"Cosplayers","status":null}	\N	2026-08-24 13:55:17	2026-08-24 13:55:17
9645ac0a-1b9e-47da-a6dd-bb024035d921	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	121	{"kind":"document_outcome","title":"Activity Calendar \\u2014 Cosplayers (1st Term 2026-2027) was approved","body":"Activity Calendar \\u2022 Cosplayers","url":"\\/activity-calendars\\/75","document_id":75,"form_type":"activity_calendar","organization":"Cosplayers","status":"approved"}	2026-08-24 14:00:32	2026-08-24 12:36:47	2026-08-24 14:00:32
2c33c0c7-1852-4ef6-a6bc-2eee3cc8feac	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	121	{"kind":"document_outcome","title":"Organization Registration \\u2014 Cosplayers (2026-2027) was returned for revision","body":"Organization Registration \\u2022 Cosplayers","url":"\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":"returned"}	2026-08-24 14:01:05	2026-08-24 05:56:06	2026-08-24 14:01:05
6e3a4c3a-ca36-4e78-b733-3f3a297b7e07	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	121	{"kind":"document_outcome","title":"Organization Registration \\u2014 Cosplayers (2026-2027) was approved","body":"Organization Registration \\u2022 Cosplayers","url":"\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":"approved"}	2026-08-24 14:01:31	2026-08-24 06:33:15	2026-08-24 14:01:31
1b16f8ae-4991-42f4-a400-f77dfe2ecdf2	App\\Notifications\\AccountVerifiedNotification	App\\Models\\User	113	{"kind":"account_verified","title":"Your SDAO account has been verified","body":"You can now submit documents and be bound to an organization.","url":"\\/dashboard","document_id":null,"form_type":null,"organization":null,"status":null}	\N	2026-08-24 14:23:30	2026-08-24 14:23:30
ead58262-a1e8-44f7-824e-8c28fd9f11ff	App\\Notifications\\AccountVerifiedNotification	App\\Models\\User	128	{"kind":"account_verified","title":"Your SDAO account has been verified","body":"You can now submit documents and be bound to an organization.","url":"\\/dashboard","document_id":null,"form_type":null,"organization":null,"status":null}	2026-08-24 14:24:01	2026-08-24 14:23:24	2026-08-24 14:24:01
ac832470-6d41-43a2-932e-319bbd373dc0	App\\Notifications\\JoinRequestReceivedNotification	App\\Models\\User	122	{"kind":"join_request_received","title":"Join request: Juancho Marudo wants to join Cosplayers","body":"Requesting to join \\u2022 Cosplayers","url":"\\/review\\/join-requests","document_id":null,"form_type":null,"organization":"Cosplayers","status":null}	\N	2026-08-24 14:30:58	2026-08-24 14:30:58
41eec8f4-9dea-4075-b524-f7aaeecd7d61	App\\Notifications\\ApproverProvisionedNotification	App\\Models\\User	129	{"kind":"approver_provisioned","title":"Your SDAO approver account has been created","body":"You've been added as Adviser. Check your email for your login details.","url":"\\/settings\\/security","document_id":null,"form_type":null,"organization":null,"status":null}	2026-08-24 14:40:01	2026-08-24 14:38:55	2026-08-24 14:40:01
d80201ba-65aa-4848-847d-b2f93add1074	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 CODECS (2026-2027)","body":"Organization Registration \\u2022 CODECS","url":"\\/review\\/registrations\\/35","document_id":35,"form_type":"organization_registration","organization":"CODECS","status":null}	2026-08-24 14:45:38	2026-08-21 05:33:09	2026-08-24 14:45:38
2c6a35e8-bbb4-4257-8835-95f3b0e03d40	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 UAPSA (2026-2027)","body":"Organization Registration \\u2022 UAPSA","url":"\\/review\\/registrations\\/36","document_id":36,"form_type":"organization_registration","organization":"UAPSA","status":null}	2026-08-24 14:45:38	2026-08-21 05:34:35	2026-08-24 14:45:38
f03073c1-16de-4192-8b0f-e29fde5a654b	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 PICE (2026-2027)","body":"Organization Registration \\u2022 PICE","url":"\\/review\\/registrations\\/37","document_id":37,"form_type":"organization_registration","organization":"PICE","status":null}	2026-08-24 14:45:38	2026-08-21 05:36:01	2026-08-24 14:45:38
4412bcf4-6d4b-46e5-bdbe-55c4e3ecaa6a	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Psychology Org (2026-2027)","body":"Organization Registration \\u2022 Psychology Org","url":"\\/review\\/registrations\\/38","document_id":38,"form_type":"organization_registration","organization":"Psychology Org","status":null}	2026-08-24 14:45:38	2026-08-21 05:37:27	2026-08-24 14:45:38
fce25254-6559-4f22-b7eb-204ad6a275bb	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 MTSC (2026-2027)","body":"Organization Registration \\u2022 MTSC","url":"\\/review\\/registrations\\/39","document_id":39,"form_type":"organization_registration","organization":"MTSC","status":null}	2026-08-24 14:45:38	2026-08-21 05:38:53	2026-08-24 14:45:38
eee4f1a5-b598-4787-9756-0a0020de8530	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 JPIA (2026-2027)","body":"Organization Registration \\u2022 JPIA","url":"\\/review\\/registrations\\/40","document_id":40,"form_type":"organization_registration","organization":"JPIA","status":null}	2026-08-24 14:45:38	2026-08-21 05:40:20	2026-08-24 14:45:38
a02624a7-2a61-4999-ae06-e5fd15ba3be7	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Red Cross Youth (2026-2027)","body":"Organization Registration \\u2022 Red Cross Youth","url":"\\/review\\/registrations\\/41","document_id":41,"form_type":"organization_registration","organization":"Red Cross Youth","status":null}	2026-08-24 14:45:38	2026-08-21 05:41:47	2026-08-24 14:45:38
62e28bcd-0846-426f-8aee-73e07e8685a8	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Venaris Esports (2026-2027)","body":"Organization Registration \\u2022 Venaris Esports","url":"\\/review\\/registrations\\/42","document_id":42,"form_type":"organization_registration","organization":"Venaris Esports","status":null}	2026-08-24 14:45:38	2026-08-21 05:43:13	2026-08-24 14:45:38
dca016fd-6397-41f5-8655-99f52de8b183	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 NEXUS (2026-2027)","body":"Organization Registration \\u2022 NEXUS","url":"\\/review\\/registrations\\/44","document_id":44,"form_type":"organization_registration","organization":"NEXUS","status":null}	2026-08-24 14:45:38	2026-08-21 05:45:00	2026-08-24 14:45:38
2944eaf4-a974-49fc-8518-48315a8af03f	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 COMEX (2026-2027)","body":"Organization Registration \\u2022 COMEX","url":"\\/review\\/registrations\\/45","document_id":45,"form_type":"organization_registration","organization":"COMEX","status":null}	2026-08-24 14:45:38	2026-08-21 05:45:56	2026-08-24 14:45:38
e5f919bd-890b-4f61-a2a7-85964098eb06	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 CREA8ives (2026-2027)","body":"Organization Registration \\u2022 CREA8ives","url":"\\/review\\/registrations\\/46","document_id":46,"form_type":"organization_registration","organization":"CREA8ives","status":null}	2026-08-24 14:45:38	2026-08-21 05:46:54	2026-08-24 14:45:38
63a2be7a-314b-461e-b97a-bece9b0ff073	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 UAPSA (2026-2027)","body":"Organization Renewal \\u2022 UAPSA","url":"\\/review\\/renewals\\/48","document_id":48,"form_type":"organization_renewal","organization":"UAPSA","status":null}	2026-08-24 14:45:38	2026-08-21 05:48:04	2026-08-24 14:45:38
aa6d5571-3e81-4be7-aa2a-34747ba6330f	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 PICE (2026-2027)","body":"Organization Renewal \\u2022 PICE","url":"\\/review\\/renewals\\/49","document_id":49,"form_type":"organization_renewal","organization":"PICE","status":null}	2026-08-24 14:45:38	2026-08-21 05:49:03	2026-08-24 14:45:38
b693040d-bcad-4d69-847c-03f3f414641a	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 JPIA (2026-2027)","body":"Organization Renewal \\u2022 JPIA","url":"\\/review\\/renewals\\/50","document_id":50,"form_type":"organization_renewal","organization":"JPIA","status":null}	2026-08-24 14:45:38	2026-08-21 05:50:12	2026-08-24 14:45:38
a1e0a70b-cd37-4e02-90cd-0f9c85448797	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 Red Cross Youth (2026-2027)","body":"Organization Renewal \\u2022 Red Cross Youth","url":"\\/review\\/renewals\\/51","document_id":51,"form_type":"organization_renewal","organization":"Red Cross Youth","status":null}	2026-08-24 14:45:38	2026-08-21 05:51:32	2026-08-24 14:45:38
bc74eab6-4672-4209-a154-ddaf80e50275	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Psychology Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Psychology Org","url":"\\/review\\/activity-calendars\\/55","document_id":55,"form_type":"activity_calendar","organization":"Psychology Org","status":null}	2026-08-24 14:45:38	2026-08-21 05:55:41	2026-08-24 14:45:38
1f828c07-72c1-42c9-b549-20ed86ae9bab	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: After-Activity Report \\u2014 Infrastructure Career Fair","body":"After-Activity Report \\u2022 PICE","url":"\\/review\\/reports\\/69","document_id":69,"form_type":"after_activity_report","organization":"PICE","status":null}	2026-08-24 14:45:38	2026-08-21 06:16:02	2026-08-24 14:45:38
637d49e4-a65e-44cb-8042-87271da18f35	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Cosplayers (2026-2027)","body":"Organization Registration \\u2022 Cosplayers","url":"\\/review\\/registrations\\/71","document_id":71,"form_type":"organization_registration","organization":"Cosplayers","status":null}	2026-08-24 14:45:38	2026-08-24 05:57:09	2026-08-24 14:45:38
92bd37a1-c272-498f-9403-d221e759b0af	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 Red Cross Youth (2026-2027)","body":"Organization Renewal \\u2022 Red Cross Youth","url":"\\/review\\/renewals\\/72","document_id":72,"form_type":"organization_renewal","organization":"Red Cross Youth","status":null}	2026-08-24 14:45:38	2026-08-24 06:30:03	2026-08-24 14:45:38
ad9b6687-7604-449b-a5d9-af9e9a407c2c	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Valorant Club (2026-2027)","body":"Organization Registration \\u2022 Valorant Club","url":"\\/review\\/registrations\\/73","document_id":73,"form_type":"organization_registration","organization":"Valorant Club","status":null}	2026-08-24 14:45:38	2026-08-24 08:49:09	2026-08-24 14:45:38
274e5ad6-c516-4485-99e5-c27af1ad945a	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Valorant Club (2026-2027)","body":"Organization Registration \\u2022 Valorant Club","url":"\\/review\\/registrations\\/74","document_id":74,"form_type":"organization_registration","organization":"Valorant Club","status":null}	2026-08-24 14:45:38	2026-08-24 09:12:18	2026-08-24 14:45:38
9b59e905-3272-481a-a932-ab693aaed866	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Valorant Club (2026-2027)","body":"Organization Registration \\u2022 Valorant Club","url":"\\/review\\/registrations\\/74","document_id":74,"form_type":"organization_registration","organization":"Valorant Club","status":null}	2026-08-24 14:45:38	2026-08-24 09:17:43	2026-08-24 14:45:38
e6986e8d-703a-4f9c-b063-313bf3c41bdb	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Valorant Club (2026-2027)","body":"Organization Registration \\u2022 Valorant Club","url":"\\/review\\/registrations\\/74","document_id":74,"form_type":"organization_registration","organization":"Valorant Club","status":null}	2026-08-24 14:45:38	2026-08-24 09:28:01	2026-08-24 14:45:38
52787855-d19b-47b2-a69b-df26b9785147	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Cosplayers (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Cosplayers","url":"\\/review\\/activity-calendars\\/75","document_id":75,"form_type":"activity_calendar","organization":"Cosplayers","status":null}	2026-08-24 14:45:38	2026-08-24 12:26:02	2026-08-24 14:45:38
ded4bee6-543f-4bf8-a03f-f0553c1312aa	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Cosplayers (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Cosplayers","url":"\\/review\\/activity-calendars\\/76","document_id":76,"form_type":"activity_calendar","organization":"Cosplayers","status":null}	2026-08-24 14:45:38	2026-08-24 12:42:46	2026-08-24 14:45:38
5890b280-bd0b-4d71-b55b-8166952d424e	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Resubmitted for your review: Activity Calendar \\u2014 Cosplayers (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Cosplayers","url":"\\/review\\/activity-calendars\\/76","document_id":76,"form_type":"activity_calendar","organization":"Cosplayers","status":null}	2026-08-24 14:45:38	2026-08-24 12:50:39	2026-08-24 14:45:38
f6c997d5-ad9d-4c43-a6d2-b27c44f398d5	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Student Leadership Workshop (Cosplayers)","body":"Activity Proposal \\u2022 Cosplayers","url":"\\/review\\/activity-proposals\\/79","document_id":79,"form_type":"activity_proposal","organization":"Cosplayers","status":null}	2026-08-24 14:45:38	2026-08-24 13:55:15	2026-08-24 14:45:38
17a763a6-ccf1-4400-a2cb-071c8583a9d6	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	40	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Accountancy Career Talk (JPIA)","body":"Activity Proposal \\u2022 JPIA","url":"\\/review\\/activity-proposals\\/80","document_id":80,"form_type":"activity_proposal","organization":"JPIA","status":null}	2026-08-24 16:24:25	2026-08-24 16:22:47	2026-08-24 16:24:25
772c78c2-bb62-42bb-ae8b-ea8f9e391b28	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	38	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Accountancy Career Talk (JPIA)","body":"Activity Proposal \\u2022 JPIA","url":"\\/review\\/activity-proposals\\/80","document_id":80,"form_type":"activity_proposal","organization":"JPIA","status":null}	\N	2026-08-24 16:25:37	2026-08-24 16:25:37
570cc71d-4439-4f9e-8c0f-5102091128bd	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	101	{"kind":"document_outcome","title":"Activity Proposal \\u2014 Accountancy Career Talk (JPIA) was returned for revision","body":"Activity Proposal \\u2022 JPIA","url":"\\/activity-proposals\\/80","document_id":80,"form_type":"activity_proposal","organization":"JPIA","status":"returned"}	\N	2026-08-24 16:34:47	2026-08-24 16:34:47
129ddb4e-31f9-405b-9f19-3f5a9304fedb	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	96	{"kind":"document_outcome","title":"Activity Proposal \\u2014 Accountancy Career Talk (JPIA) was returned for revision","body":"Activity Proposal \\u2022 JPIA","url":"\\/activity-proposals\\/80","document_id":80,"form_type":"activity_proposal","organization":"JPIA","status":"returned"}	\N	2026-08-24 16:34:48	2026-08-24 16:34:48
46731120-1ce6-49f6-8e89-0d26335b8118	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	38	{"kind":"approver_hand_off","title":"Resubmitted for your review: Activity Proposal \\u2014 Accountancy Career Talk (JPIA)","body":"Activity Proposal \\u2022 JPIA","url":"\\/review\\/activity-proposals\\/80","document_id":80,"form_type":"activity_proposal","organization":"JPIA","status":null}	\N	2026-08-24 16:37:14	2026-08-24 16:37:14
45c3a260-10c8-44f8-aded-09db47b0fb8f	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	36	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Accountancy Career Talk (JPIA)","body":"Activity Proposal \\u2022 JPIA","url":"\\/review\\/activity-proposals\\/80","document_id":80,"form_type":"activity_proposal","organization":"JPIA","status":null}	\N	2026-08-24 16:38:04	2026-08-24 16:38:04
fed99320-5c31-4526-ac6a-d87b8972a7f2	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Accountancy Career Talk (JPIA)","body":"Activity Proposal \\u2022 JPIA","url":"\\/review\\/activity-proposals\\/80","document_id":80,"form_type":"activity_proposal","organization":"JPIA","status":null}	\N	2026-08-24 16:41:27	2026-08-24 16:41:27
81050c61-c448-4bfb-bca1-d2cecc158997	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Accountancy Career Talk (JPIA)","body":"Activity Proposal \\u2022 JPIA","url":"\\/review\\/activity-proposals\\/80","document_id":80,"form_type":"activity_proposal","organization":"JPIA","status":null}	\N	2026-08-24 16:41:30	2026-08-24 16:41:30
fb11981e-6a56-4f5d-bd43-ab307ee01c31	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Accountancy Career Talk (JPIA)","body":"Activity Proposal \\u2022 JPIA","url":"\\/review\\/activity-proposals\\/80","document_id":80,"form_type":"activity_proposal","organization":"JPIA","status":null}	\N	2026-08-24 16:41:32	2026-08-24 16:41:32
575b7529-301d-4bd5-b367-59661cd82bb0	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Accountancy Career Talk (JPIA)","body":"Activity Proposal \\u2022 JPIA","url":"\\/review\\/activity-proposals\\/80","document_id":80,"form_type":"activity_proposal","organization":"JPIA","status":null}	\N	2026-08-24 16:41:35	2026-08-24 16:41:35
8576ac71-bdad-4e24-9dc8-d9eede537bc5	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	23	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Accountancy Career Talk (JPIA)","body":"Activity Proposal \\u2022 JPIA","url":"\\/review\\/activity-proposals\\/80","document_id":80,"form_type":"activity_proposal","organization":"JPIA","status":null}	\N	2026-08-24 16:47:55	2026-08-24 16:47:55
f838a1cd-249e-4002-abc4-ea5680b77e49	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	24	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Accountancy Career Talk (JPIA)","body":"Activity Proposal \\u2022 JPIA","url":"\\/review\\/activity-proposals\\/80","document_id":80,"form_type":"activity_proposal","organization":"JPIA","status":null}	\N	2026-08-24 16:51:19	2026-08-24 16:51:19
e4cc7064-ae76-4fb7-95af-4530ce27e28f	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	25	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Accountancy Career Talk (JPIA)","body":"Activity Proposal \\u2022 JPIA","url":"\\/review\\/activity-proposals\\/80","document_id":80,"form_type":"activity_proposal","organization":"JPIA","status":null}	\N	2026-08-24 16:53:05	2026-08-24 16:53:05
f3ea0c35-0a0f-43a9-af7a-73abb6533b47	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	101	{"kind":"document_outcome","title":"Activity Proposal \\u2014 Accountancy Career Talk (JPIA) was approved","body":"Activity Proposal \\u2022 JPIA","url":"\\/activity-proposals\\/80","document_id":80,"form_type":"activity_proposal","organization":"JPIA","status":"approved"}	\N	2026-08-24 16:54:37	2026-08-24 16:54:37
fd090383-90eb-4fbf-ade6-3805e15303e1	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	96	{"kind":"document_outcome","title":"Activity Proposal \\u2014 Accountancy Career Talk (JPIA) was approved","body":"Activity Proposal \\u2022 JPIA","url":"\\/activity-proposals\\/80","document_id":80,"form_type":"activity_proposal","organization":"JPIA","status":"approved"}	2026-08-24 16:55:12	2026-08-24 16:54:39	2026-08-24 16:55:12
5b6551a3-906c-4904-9345-663683684ce3	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: After-Activity Report \\u2014 Accountancy Career Talk","body":"After-Activity Report \\u2022 JPIA","url":"\\/review\\/reports\\/81","document_id":81,"form_type":"after_activity_report","organization":"JPIA","status":null}	\N	2026-08-24 17:06:13	2026-08-24 17:06:13
df823540-5f94-484c-9ed7-594372bd22de	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: After-Activity Report \\u2014 Accountancy Career Talk","body":"After-Activity Report \\u2022 JPIA","url":"\\/review\\/reports\\/81","document_id":81,"form_type":"after_activity_report","organization":"JPIA","status":null}	\N	2026-08-24 17:06:14	2026-08-24 17:06:14
66d0fd93-83c9-4e30-aa6b-41544d0f6596	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: After-Activity Report \\u2014 Accountancy Career Talk","body":"After-Activity Report \\u2022 JPIA","url":"\\/review\\/reports\\/81","document_id":81,"form_type":"after_activity_report","organization":"JPIA","status":null}	\N	2026-08-24 17:06:16	2026-08-24 17:06:16
b8147066-013d-4c77-a32c-a5d03819ab6d	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: After-Activity Report \\u2014 Accountancy Career Talk","body":"After-Activity Report \\u2022 JPIA","url":"\\/review\\/reports\\/81","document_id":81,"form_type":"after_activity_report","organization":"JPIA","status":null}	\N	2026-08-24 17:06:18	2026-08-24 17:06:18
5ed3904a-b341-40df-b809-bdd05e5ec1c5	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	101	{"kind":"document_outcome","title":"After-Activity Report \\u2014 Accountancy Career Talk was approved","body":"After-Activity Report \\u2022 JPIA","url":"\\/reports\\/81","document_id":81,"form_type":"after_activity_report","organization":"JPIA","status":"approved"}	\N	2026-08-24 17:08:39	2026-08-24 17:08:39
61a50e64-377d-4fd5-8c57-1754ddd6a6d8	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	96	{"kind":"document_outcome","title":"After-Activity Report \\u2014 Accountancy Career Talk was approved","body":"After-Activity Report \\u2022 JPIA","url":"\\/reports\\/81","document_id":81,"form_type":"after_activity_report","organization":"JPIA","status":"approved"}	\N	2026-08-24 17:08:41	2026-08-24 17:08:41
3c891296-5634-43f0-a69a-b2be84461bea	App\\Notifications\\AccountVerifiedNotification	App\\Models\\User	130	{"kind":"account_verified","title":"Your SDAO account has been verified","body":"You can now submit documents and be bound to an organization.","url":"\\/dashboard","document_id":null,"form_type":null,"organization":null,"status":null}	\N	2026-08-29 14:13:35	2026-08-29 14:13:35
6e2fd479-b04e-4b0d-b964-f9a486e5864f	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Dota Club (2026-2027)","body":"Organization Registration \\u2022 Dota Club","url":"\\/review\\/registrations\\/82","document_id":82,"form_type":"organization_registration","organization":"Dota Club","status":null}	\N	2026-08-29 14:15:52	2026-08-29 14:15:52
c63c63a7-50fa-4d2e-801a-510739ebb44a	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Dota Club (2026-2027)","body":"Organization Registration \\u2022 Dota Club","url":"\\/review\\/registrations\\/82","document_id":82,"form_type":"organization_registration","organization":"Dota Club","status":null}	\N	2026-08-29 14:15:53	2026-08-29 14:15:53
4c552315-becd-424c-aa4a-25be1f83f406	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Dota Club (2026-2027)","body":"Organization Registration \\u2022 Dota Club","url":"\\/review\\/registrations\\/82","document_id":82,"form_type":"organization_registration","organization":"Dota Club","status":null}	\N	2026-08-29 14:15:55	2026-08-29 14:15:55
91826641-b01b-4627-8f6b-6460a962a6db	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Dota Club (2026-2027)","body":"Organization Registration \\u2022 Dota Club","url":"\\/review\\/registrations\\/82","document_id":82,"form_type":"organization_registration","organization":"Dota Club","status":null}	\N	2026-08-29 14:15:57	2026-08-29 14:15:57
29bb2ef9-497e-4b97-a257-17a8fd2dba4b	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	130	{"kind":"document_outcome","title":"Organization Registration \\u2014 Dota Club (2026-2027) was approved","body":"Organization Registration \\u2022 Dota Club","url":"\\/registrations\\/82","document_id":82,"form_type":"organization_registration","organization":"Dota Club","status":"approved"}	\N	2026-08-29 14:20:19	2026-08-29 14:20:19
446a00c4-e4bb-4e00-b882-609237f36ce4	App\\Notifications\\JoinRequestApprovedNotification	App\\Models\\User	128	{"kind":"join_request_approved","title":"You're in \\u2014 Cosplayers","body":"Your request to join was approved. You now have officer access.","url":"\\/dashboard","document_id":null,"form_type":null,"organization":"Cosplayers","status":"approved"}	2026-08-29 15:05:31	2026-08-24 16:27:55	2026-08-29 15:05:31
58c94e40-abf4-4c41-844f-4aea4f5c2e14	App\\Notifications\\JoinRequestReceivedNotification	App\\Models\\User	121	{"kind":"join_request_received","title":"Join request: Juancho Marudo wants to join Cosplayers","body":"Requesting to join \\u2022 Cosplayers","url":"\\/review\\/join-requests","document_id":null,"form_type":null,"organization":"Cosplayers","status":null}	2026-09-02 09:13:14	2026-08-24 14:30:56	2026-09-02 09:13:14
1b6c75cc-afac-4040-b3dd-8b7c0d976458	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	29	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Sept 9 Act (Dayether's Org)","body":"Activity Proposal \\u2022 Dayether's Org","url":"\\/review\\/activity-proposals\\/103","document_id":103,"form_type":"activity_proposal","organization":"Dayether's Org","status":null}	\N	2026-09-03 10:00:20	2026-09-03 10:00:20
14065f0a-ee96-43c9-8a5f-97a66a5d7953	App\\Notifications\\AccountVerifiedNotification	App\\Models\\User	131	{"kind":"account_verified","title":"Your SDAO account has been verified","body":"You can now submit documents and be bound to an organization.","url":"\\/dashboard","document_id":null,"form_type":null,"organization":null,"status":null}	\N	2026-08-30 02:34:27	2026-08-30 02:34:27
06edfc9e-48c9-4148-b7fa-fb6f3006c926	App\\Notifications\\ApproverProvisionedNotification	App\\Models\\User	132	{"kind":"approver_provisioned","title":"Your SDAO approver account has been created","body":"You've been added as Adviser. Check your email for your login details.","url":"\\/settings\\/security","document_id":null,"form_type":null,"organization":null,"status":null}	\N	2026-08-30 02:41:30	2026-08-30 02:41:30
2dac8a33-e09e-4c68-bcb6-2fead124d095	App\\Notifications\\AccountVerifiedNotification	App\\Models\\User	139	{"kind":"account_verified","title":"Your SDAO account has been verified","body":"You can now submit documents and be bound to an organization.","url":"\\/dashboard","document_id":null,"form_type":null,"organization":null,"status":null}	\N	2026-09-03 08:01:46	2026-09-03 08:01:46
21d536c2-b9d6-4cac-ad9b-40d20ecf50f2	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	139	{"kind":"document_outcome","title":"Organization Registration \\u2014 Dayether's Org (2026-2027) was approved","body":"Organization Registration \\u2022 Dayether's Org","url":"\\/registrations\\/90","document_id":90,"form_type":"organization_registration","organization":"Dayether's Org","status":"approved"}	\N	2026-09-03 08:07:09	2026-09-03 08:07:09
ff5d7536-3b8e-4f6f-8c2f-009e74a5b92d	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	140	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Sept 9 Act (Dayether's Org)","body":"Activity Proposal \\u2022 Dayether's Org","url":"\\/review\\/activity-proposals\\/102","document_id":102,"form_type":"activity_proposal","organization":"Dayether's Org","status":null}	\N	2026-09-05 11:01:46	2026-09-05 11:01:46
df773cbe-6bb2-45d8-8a81-7cd9f3f2357d	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	29	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Sept 9 Act (Dayether's Org)","body":"Activity Proposal \\u2022 Dayether's Org","url":"\\/review\\/activity-proposals\\/102","document_id":102,"form_type":"activity_proposal","organization":"Dayether's Org","status":null}	\N	2026-09-05 11:03:35	2026-09-05 11:03:35
f2328d52-782b-4c05-bc61-ec2e06e4c2f3	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Sept 9 Act (Dayether's Org)","body":"Activity Proposal \\u2022 Dayether's Org","url":"\\/review\\/activity-proposals\\/102","document_id":102,"form_type":"activity_proposal","organization":"Dayether's Org","status":null}	\N	2026-09-05 11:06:41	2026-09-05 11:06:41
7512f62a-a31c-4a65-81dc-a5a4c1a2dea2	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Sept 9 Act (Dayether's Org)","body":"Activity Proposal \\u2022 Dayether's Org","url":"\\/review\\/activity-proposals\\/102","document_id":102,"form_type":"activity_proposal","organization":"Dayether's Org","status":null}	\N	2026-09-05 11:06:41	2026-09-05 11:06:41
90fa6450-3b13-4aff-9c5d-afd9eaba1dfe	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Sept 9 Act (Dayether's Org)","body":"Activity Proposal \\u2022 Dayether's Org","url":"\\/review\\/activity-proposals\\/102","document_id":102,"form_type":"activity_proposal","organization":"Dayether's Org","status":null}	\N	2026-09-05 11:06:41	2026-09-05 11:06:41
9046540c-d78b-4eb6-b7fa-8562c2118bb5	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Sept 9 Act (Dayether's Org)","body":"Activity Proposal \\u2022 Dayether's Org","url":"\\/review\\/activity-proposals\\/102","document_id":102,"form_type":"activity_proposal","organization":"Dayether's Org","status":null}	\N	2026-09-05 11:06:41	2026-09-05 11:06:41
fab9102f-ab1e-4731-b945-71bf95560897	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	23	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Sept 9 Act (Dayether's Org)","body":"Activity Proposal \\u2022 Dayether's Org","url":"\\/review\\/activity-proposals\\/102","document_id":102,"form_type":"activity_proposal","organization":"Dayether's Org","status":null}	\N	2026-09-05 11:07:43	2026-09-05 11:07:43
52b5182a-c9ae-4916-b730-6e7ac5be1d7f	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Team Liquid PH (2026-2027)","body":"Organization Registration \\u2022 Team Liquid PH","url":"\\/review\\/registrations\\/83","document_id":83,"form_type":"organization_registration","organization":"Team Liquid PH","status":null}	\N	2026-08-30 02:54:06	2026-08-30 02:54:06
221cd646-7323-43d1-844f-8c56253b4c45	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Team Liquid PH (2026-2027)","body":"Organization Registration \\u2022 Team Liquid PH","url":"\\/review\\/registrations\\/83","document_id":83,"form_type":"organization_registration","organization":"Team Liquid PH","status":null}	\N	2026-08-30 02:54:06	2026-08-30 02:54:06
18e91a88-9d1e-4138-942a-a9055a4a5cdd	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Team Liquid PH (2026-2027)","body":"Organization Registration \\u2022 Team Liquid PH","url":"\\/review\\/registrations\\/83","document_id":83,"form_type":"organization_registration","organization":"Team Liquid PH","status":null}	\N	2026-08-30 02:54:06	2026-08-30 02:54:06
18194067-c02c-439f-830c-eeb54329ef39	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Team Liquid PH (2026-2027)","body":"Organization Registration \\u2022 Team Liquid PH","url":"\\/review\\/registrations\\/83","document_id":83,"form_type":"organization_registration","organization":"Team Liquid PH","status":null}	\N	2026-08-30 02:54:06	2026-08-30 02:54:06
4df2ad99-9501-4d80-b17d-b73b699d3265	App\\Notifications\\ApproverProvisionedNotification	App\\Models\\User	140	{"kind":"approver_provisioned","title":"Your SDAO approver account has been created","body":"You've been added as Adviser. Check your email for your login details.","url":"\\/settings\\/security","document_id":null,"form_type":null,"organization":null,"status":null}	\N	2026-09-03 08:05:20	2026-09-03 08:05:20
a2dbdf9d-a192-475d-8c71-5fb778e55296	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Dayether's Org (2026-2027)","body":"Organization Registration \\u2022 Dayether's Org","url":"\\/review\\/registrations\\/90","document_id":90,"form_type":"organization_registration","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:06:37	2026-09-03 08:06:37
096ab2eb-f24b-4b78-a6f9-be89083642b3	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Dayether's Org (2026-2027)","body":"Organization Registration \\u2022 Dayether's Org","url":"\\/review\\/registrations\\/90","document_id":90,"form_type":"organization_registration","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:06:37	2026-09-03 08:06:37
2d99dcaa-b1a7-42cb-ad2b-8978c8cdc453	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Dayether's Org (2026-2027)","body":"Organization Registration \\u2022 Dayether's Org","url":"\\/review\\/registrations\\/90","document_id":90,"form_type":"organization_registration","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:06:38	2026-09-03 08:06:38
d8757663-58aa-47df-8629-bad12ce61118	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Dayether's Org (2026-2027)","body":"Organization Registration \\u2022 Dayether's Org","url":"\\/review\\/registrations\\/90","document_id":90,"form_type":"organization_registration","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:06:38	2026-09-03 08:06:38
e0422aed-4b82-4e9a-845a-02b045bd52e0	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	27	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Sept 9 Act (Dayether's Org)","body":"Activity Proposal \\u2022 Dayether's Org","url":"\\/review\\/activity-proposals\\/102","document_id":102,"form_type":"activity_proposal","organization":"Dayether's Org","status":null}	\N	2026-09-05 11:06:17	2026-09-05 11:06:17
40ed4c6a-9a4a-41fe-9d2e-9a3246cff0d8	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	131	{"kind":"document_outcome","title":"Organization Registration \\u2014 Team Liquid PH (2026-2027) was rejected","body":"Organization Registration \\u2022 Team Liquid PH","url":"\\/registrations\\/83","document_id":83,"form_type":"organization_registration","organization":"Team Liquid PH","status":"rejected"}	\N	2026-08-30 03:45:06	2026-08-30 03:45:06
ed98ab0e-c665-4d2c-be59-f6e85855a83d	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 TLPH (2026-2027)","body":"Organization Registration \\u2022 TLPH","url":"\\/review\\/registrations\\/84","document_id":84,"form_type":"organization_registration","organization":"TLPH","status":null}	\N	2026-08-30 03:46:44	2026-08-30 03:46:44
84f68ef4-ac61-4334-a965-ac6a5a88c6c5	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 TLPH (2026-2027)","body":"Organization Registration \\u2022 TLPH","url":"\\/review\\/registrations\\/84","document_id":84,"form_type":"organization_registration","organization":"TLPH","status":null}	\N	2026-08-30 03:46:44	2026-08-30 03:46:44
d7b5895f-26d2-4077-a61f-9d39fb19c65c	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 TLPH (2026-2027)","body":"Organization Registration \\u2022 TLPH","url":"\\/review\\/registrations\\/84","document_id":84,"form_type":"organization_registration","organization":"TLPH","status":null}	\N	2026-08-30 03:46:44	2026-08-30 03:46:44
84c0beae-a91b-4d0c-a485-da5277b3c0a8	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 TLPH (2026-2027)","body":"Organization Registration \\u2022 TLPH","url":"\\/review\\/registrations\\/84","document_id":84,"form_type":"organization_registration","organization":"TLPH","status":null}	\N	2026-08-30 03:46:44	2026-08-30 03:46:44
f3eca27a-04b1-481a-9b85-a27ba6b7d2ce	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 Dayether's Org (2026-2027)","body":"Organization Renewal \\u2022 Dayether's Org","url":"\\/review\\/renewals\\/91","document_id":91,"form_type":"organization_renewal","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:08:03	2026-09-03 08:08:03
a07d962f-0dd8-42ab-94b3-90caff4beb3a	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 Dayether's Org (2026-2027)","body":"Organization Renewal \\u2022 Dayether's Org","url":"\\/review\\/renewals\\/91","document_id":91,"form_type":"organization_renewal","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:08:03	2026-09-03 08:08:03
e2c2bf68-5ae7-4dae-98cc-bbd56a73b965	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 Dayether's Org (2026-2027)","body":"Organization Renewal \\u2022 Dayether's Org","url":"\\/review\\/renewals\\/91","document_id":91,"form_type":"organization_renewal","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:08:03	2026-09-03 08:08:03
2ec5ae2a-2826-47a7-a56e-86f3b63c3983	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 Dayether's Org (2026-2027)","body":"Organization Renewal \\u2022 Dayether's Org","url":"\\/review\\/renewals\\/91","document_id":91,"form_type":"organization_renewal","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:08:03	2026-09-03 08:08:03
63ca9962-53a8-4039-944b-6b9b48abace5	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 Dayether's Org (2026-2027)","body":"Organization Renewal \\u2022 Dayether's Org","url":"\\/review\\/renewals\\/92","document_id":92,"form_type":"organization_renewal","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:10:23	2026-09-03 08:10:23
41b15854-8a7d-4775-b67f-3c9c4aa5056d	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 Dayether's Org (2026-2027)","body":"Organization Renewal \\u2022 Dayether's Org","url":"\\/review\\/renewals\\/92","document_id":92,"form_type":"organization_renewal","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:10:23	2026-09-03 08:10:23
2ac5144b-68ba-4655-9fce-01e4fa92847c	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 Dayether's Org (2026-2027)","body":"Organization Renewal \\u2022 Dayether's Org","url":"\\/review\\/renewals\\/92","document_id":92,"form_type":"organization_renewal","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:10:23	2026-09-03 08:10:23
273f0b97-87e4-470b-b84f-378aff30dcfc	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 Dayether's Org (2026-2027)","body":"Organization Renewal \\u2022 Dayether's Org","url":"\\/review\\/renewals\\/92","document_id":92,"form_type":"organization_renewal","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:10:23	2026-09-03 08:10:23
9f0a982f-8023-4d2a-8e53-fc839dc429c6	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	139	{"kind":"document_outcome","title":"Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027) was rejected","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/activity-calendars\\/93","document_id":93,"form_type":"activity_calendar","organization":"Dayether's Org","status":"rejected"}	\N	2026-09-03 08:21:17	2026-09-03 08:21:17
c05df616-c795-4771-aea7-4b396b788a0f	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/94","document_id":94,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:22:06	2026-09-03 08:22:06
d770c0e0-633a-4795-8e8c-453fee494941	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/94","document_id":94,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:22:06	2026-09-03 08:22:06
f9a5f939-c706-4e1b-893a-ff179f384316	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/94","document_id":94,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:22:06	2026-09-03 08:22:06
b8400a2b-765c-418e-acb1-0d1dcaf11772	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/94","document_id":94,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:22:06	2026-09-03 08:22:06
ee3d4edc-9d94-4102-8d9b-5037f20b1813	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	139	{"kind":"document_outcome","title":"Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027) was rejected","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/activity-calendars\\/94","document_id":94,"form_type":"activity_calendar","organization":"Dayether's Org","status":"rejected"}	\N	2026-09-03 08:23:29	2026-09-03 08:23:29
8afcf7b7-7af4-434d-9ec9-dfcf38af60b7	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	131	{"kind":"document_outcome","title":"Organization Registration \\u2014 TLPH (2026-2027) was returned for revision","body":"Organization Registration \\u2022 TLPH","url":"\\/registrations\\/84","document_id":84,"form_type":"organization_registration","organization":"TLPH","status":"returned"}	\N	2026-08-30 03:47:36	2026-08-30 03:47:36
ce4fbea8-5d5f-48e3-8792-2d3f65403021	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 TLPH (2026-2027)","body":"Organization Registration \\u2022 TLPH","url":"\\/review\\/registrations\\/84","document_id":84,"form_type":"organization_registration","organization":"TLPH","status":null}	\N	2026-08-30 03:48:16	2026-08-30 03:48:16
390cbcf2-1e49-402b-b303-9f987970293d	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 TLPH (2026-2027)","body":"Organization Registration \\u2022 TLPH","url":"\\/review\\/registrations\\/84","document_id":84,"form_type":"organization_registration","organization":"TLPH","status":null}	\N	2026-08-30 03:48:16	2026-08-30 03:48:16
3e1fc2fd-6bd0-4042-b065-8b470e5071cd	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 TLPH (2026-2027)","body":"Organization Registration \\u2022 TLPH","url":"\\/review\\/registrations\\/84","document_id":84,"form_type":"organization_registration","organization":"TLPH","status":null}	\N	2026-08-30 03:48:16	2026-08-30 03:48:16
ce9f8fad-5722-412f-99f5-3c7171dc9166	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 TLPH (2026-2027)","body":"Organization Registration \\u2022 TLPH","url":"\\/review\\/registrations\\/84","document_id":84,"form_type":"organization_registration","organization":"TLPH","status":null}	\N	2026-08-30 03:48:16	2026-08-30 03:48:16
8b8daa29-4fec-40cd-be9d-a83c9872e636	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 TLPH (2026-2027)","body":"Organization Registration \\u2022 TLPH","url":"\\/review\\/registrations\\/84","document_id":84,"form_type":"organization_registration","organization":"TLPH","status":null}	\N	2026-08-30 03:51:12	2026-08-30 03:51:12
5e087489-b54d-4656-868b-96219e77a5a4	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 TLPH (2026-2027)","body":"Organization Registration \\u2022 TLPH","url":"\\/review\\/registrations\\/84","document_id":84,"form_type":"organization_registration","organization":"TLPH","status":null}	\N	2026-08-30 03:51:12	2026-08-30 03:51:12
cfa58ce0-b7f6-46b4-bf57-8b2b1d097552	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 TLPH (2026-2027)","body":"Organization Registration \\u2022 TLPH","url":"\\/review\\/registrations\\/84","document_id":84,"form_type":"organization_registration","organization":"TLPH","status":null}	\N	2026-08-30 03:51:12	2026-08-30 03:51:12
a40c0c3c-6d6c-4ec0-b80f-179c08d6e6fe	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 TLPH (2026-2027)","body":"Organization Registration \\u2022 TLPH","url":"\\/review\\/registrations\\/84","document_id":84,"form_type":"organization_registration","organization":"TLPH","status":null}	\N	2026-08-30 03:51:12	2026-08-30 03:51:12
2b4fd831-2d24-428a-880a-477a905ca6a3	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	131	{"kind":"document_outcome","title":"Organization Registration \\u2014 TLPH (2026-2027) was returned for revision","body":"Organization Registration \\u2022 TLPH","url":"\\/registrations\\/84","document_id":84,"form_type":"organization_registration","organization":"TLPH","status":"returned"}	2026-08-30 03:53:11	2026-08-30 03:49:43	2026-08-30 03:53:11
2cc53c7f-3089-4d45-a149-bec5ccb8234b	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	139	{"kind":"document_outcome","title":"Organization Renewal \\u2014 Dayether's Org (2026-2027) was rejected","body":"Organization Renewal \\u2022 Dayether's Org","url":"\\/renewals\\/91","document_id":91,"form_type":"organization_renewal","organization":"Dayether's Org","status":"rejected"}	\N	2026-09-03 08:08:51	2026-09-03 08:08:51
48f53381-8228-4bfa-b536-516a9be906f6	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	24	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Sept 9 Act (Dayether's Org)","body":"Activity Proposal \\u2022 Dayether's Org","url":"\\/review\\/activity-proposals\\/102","document_id":102,"form_type":"activity_proposal","organization":"Dayether's Org","status":null}	\N	2026-09-05 13:20:46	2026-09-05 13:20:46
ade03e8a-8b2a-4568-853c-f0c88c385c8b	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	25	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Sept 9 Act (Dayether's Org)","body":"Activity Proposal \\u2022 Dayether's Org","url":"\\/review\\/activity-proposals\\/102","document_id":102,"form_type":"activity_proposal","organization":"Dayether's Org","status":null}	\N	2026-09-05 13:21:15	2026-09-05 13:21:15
1450cf61-d65f-445c-b319-c74a24165cf7	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	139	{"kind":"document_outcome","title":"Activity Proposal \\u2014 Sept 9 Act (Dayether's Org) was approved","body":"Activity Proposal \\u2022 Dayether's Org","url":"\\/activity-proposals\\/102","document_id":102,"form_type":"activity_proposal","organization":"Dayether's Org","status":"approved"}	\N	2026-09-05 13:21:59	2026-09-05 13:21:59
160e1295-0293-48e5-8dbf-25b5fc2e1d44	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	131	{"kind":"document_outcome","title":"Organization Registration \\u2014 TLPH (2026-2027) was approved","body":"Organization Registration \\u2022 TLPH","url":"\\/registrations\\/84","document_id":84,"form_type":"organization_registration","organization":"TLPH","status":"approved"}	\N	2026-08-30 03:56:43	2026-08-30 03:56:43
dcda2717-a20b-41a6-b885-c1a47c70bcfc	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	139	{"kind":"document_outcome","title":"Organization Renewal \\u2014 Dayether's Org (2026-2027) was approved","body":"Organization Renewal \\u2022 Dayether's Org","url":"\\/renewals\\/92","document_id":92,"form_type":"organization_renewal","organization":"Dayether's Org","status":"approved"}	\N	2026-09-03 08:11:10	2026-09-03 08:11:10
d3be79db-d75b-434a-9cde-a92fe83f8fe3	App\\Notifications\\ApproverProvisionedNotification	App\\Models\\User	142	{"kind":"approver_provisioned","title":"Your SDAO approver account has been created","body":"You've been added as Adviser. Check your email for your login details.","url":"\\/settings\\/security","document_id":null,"form_type":null,"organization":null,"status":null}	\N	2026-09-05 13:36:45	2026-09-05 13:36:45
274477c2-d97e-4283-ab35-fed071ca51c4	App\\Notifications\\AccountVerifiedNotification	App\\Models\\User	141	{"kind":"account_verified","title":"Your SDAO account has been verified","body":"You can now submit documents and be bound to an organization.","url":"\\/dashboard","document_id":null,"form_type":null,"organization":null,"status":null}	\N	2026-09-05 13:36:52	2026-09-05 13:36:52
5f51603a-e0de-4e51-9754-84c82c8a5706	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Gen Org (2026-2027)","body":"Organization Registration \\u2022 Gen Org","url":"\\/review\\/registrations\\/104","document_id":104,"form_type":"organization_registration","organization":"Gen Org","status":null}	\N	2026-09-05 13:39:55	2026-09-05 13:39:55
fd9db59b-7310-4764-ace7-d7b40688de4b	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Gen Org (2026-2027)","body":"Organization Registration \\u2022 Gen Org","url":"\\/review\\/registrations\\/104","document_id":104,"form_type":"organization_registration","organization":"Gen Org","status":null}	\N	2026-09-05 13:39:55	2026-09-05 13:39:55
1f44c787-737b-41b7-9f8a-8f6380900379	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Gen Org (2026-2027)","body":"Organization Registration \\u2022 Gen Org","url":"\\/review\\/registrations\\/104","document_id":104,"form_type":"organization_registration","organization":"Gen Org","status":null}	\N	2026-09-05 13:39:56	2026-09-05 13:39:56
28edbb4f-994a-4330-bfd1-b896bccc475e	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Gen Org (2026-2027)","body":"Organization Registration \\u2022 Gen Org","url":"\\/review\\/registrations\\/104","document_id":104,"form_type":"organization_registration","organization":"Gen Org","status":null}	\N	2026-09-05 13:39:56	2026-09-05 13:39:56
6e1aecc0-c1e5-4c20-a294-1e051135718e	App\\Notifications\\AccountVerifiedNotification	App\\Models\\User	133	{"kind":"account_verified","title":"Your SDAO account has been verified","body":"You can now submit documents and be bound to an organization.","url":"\\/dashboard","document_id":null,"form_type":null,"organization":null,"status":null}	\N	2026-08-30 04:12:19	2026-08-30 04:12:19
3def44bb-8d96-4189-b5c7-7e053e49a5c7	App\\Notifications\\ApproverProvisionedNotification	App\\Models\\User	134	{"kind":"approver_provisioned","title":"Your SDAO approver account has been created","body":"You've been added as Adviser. Check your email for your login details.","url":"\\/settings\\/security","document_id":null,"form_type":null,"organization":null,"status":null}	\N	2026-08-30 04:12:54	2026-08-30 04:12:54
56417ffc-fcc2-4c1b-b3eb-c37e6bbd6a8f	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Testing Org (2026-2027)","body":"Organization Registration \\u2022 Testing Org","url":"\\/review\\/registrations\\/85","document_id":85,"form_type":"organization_registration","organization":"Testing Org","status":null}	\N	2026-08-30 04:13:48	2026-08-30 04:13:48
f32b09ca-a095-46cd-9220-aa40779084f6	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Testing Org (2026-2027)","body":"Organization Registration \\u2022 Testing Org","url":"\\/review\\/registrations\\/85","document_id":85,"form_type":"organization_registration","organization":"Testing Org","status":null}	\N	2026-08-30 04:13:48	2026-08-30 04:13:48
acc0c304-7a01-4cf6-98b8-68d94a705ae3	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Testing Org (2026-2027)","body":"Organization Registration \\u2022 Testing Org","url":"\\/review\\/registrations\\/85","document_id":85,"form_type":"organization_registration","organization":"Testing Org","status":null}	\N	2026-08-30 04:13:48	2026-08-30 04:13:48
9000d02d-edab-4815-8f89-8d21245bf407	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Testing Org (2026-2027)","body":"Organization Registration \\u2022 Testing Org","url":"\\/review\\/registrations\\/85","document_id":85,"form_type":"organization_registration","organization":"Testing Org","status":null}	\N	2026-08-30 04:13:48	2026-08-30 04:13:48
dd7ae51c-4673-482b-a609-2e67c267574f	App\\Notifications\\AccountVerifiedNotification	App\\Models\\User	135	{"kind":"account_verified","title":"Your SDAO account has been verified","body":"You can now submit documents and be bound to an organization.","url":"\\/dashboard","document_id":null,"form_type":null,"organization":null,"status":null}	\N	2026-08-30 04:15:05	2026-08-30 04:15:05
b983f400-767c-4646-b8bd-5b16e012bc61	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Testing Org 2 (2026-2027)","body":"Organization Registration \\u2022 Testing Org 2","url":"\\/review\\/registrations\\/86","document_id":86,"form_type":"organization_registration","organization":"Testing Org 2","status":null}	\N	2026-08-30 04:16:10	2026-08-30 04:16:10
d944fcb7-09fd-43e6-a416-61a4be603911	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Testing Org 2 (2026-2027)","body":"Organization Registration \\u2022 Testing Org 2","url":"\\/review\\/registrations\\/86","document_id":86,"form_type":"organization_registration","organization":"Testing Org 2","status":null}	\N	2026-08-30 04:16:10	2026-08-30 04:16:10
38172219-1d76-4789-9b80-03caff199f00	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Testing Org 2 (2026-2027)","body":"Organization Registration \\u2022 Testing Org 2","url":"\\/review\\/registrations\\/86","document_id":86,"form_type":"organization_registration","organization":"Testing Org 2","status":null}	\N	2026-08-30 04:16:10	2026-08-30 04:16:10
e46898a5-b8eb-4a68-a789-ac314a675918	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Testing Org 2 (2026-2027)","body":"Organization Registration \\u2022 Testing Org 2","url":"\\/review\\/registrations\\/86","document_id":86,"form_type":"organization_registration","organization":"Testing Org 2","status":null}	\N	2026-08-30 04:16:10	2026-08-30 04:16:10
c1f438d7-138e-48a5-90ec-b38696852035	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/93","document_id":93,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:20:31	2026-09-03 08:20:31
aba43e73-153c-4d1e-97f0-8060bedd6808	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/93","document_id":93,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:20:31	2026-09-03 08:20:31
e33a6a35-1fd5-4f4a-84f3-70a41b283e60	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/93","document_id":93,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:20:31	2026-09-03 08:20:31
30fe6e03-cb38-4d34-95f5-5343a4308846	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/93","document_id":93,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:20:31	2026-09-03 08:20:31
f5f58cdd-4b27-4ae8-88d9-06685b06df0e	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	137	{"kind":"document_outcome","title":"Activity Calendar \\u2014 Extra Curricular Testing (1st Term 2026-2027) was approved","body":"Activity Calendar \\u2022 Extra Curricular Testing","url":"\\/activity-calendars\\/96","document_id":96,"form_type":"activity_calendar","organization":"Extra Curricular Testing","status":"approved"}	\N	2026-09-03 08:31:42	2026-09-03 08:31:42
bf5ee288-a96f-458d-b805-6c3d1fbdf2b9	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	141	{"kind":"document_outcome","title":"Organization Registration \\u2014 Gen Org (2026-2027) was approved","body":"Organization Registration \\u2022 Gen Org","url":"\\/registrations\\/104","document_id":104,"form_type":"organization_registration","organization":"Gen Org","status":"approved"}	\N	2026-09-05 13:41:50	2026-09-05 13:41:50
1de32932-02cf-417f-b8bb-6c583707bfed	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	133	{"kind":"document_outcome","title":"Organization Registration \\u2014 Testing Org (2026-2027) was approved","body":"Organization Registration \\u2022 Testing Org","url":"\\/registrations\\/85","document_id":85,"form_type":"organization_registration","organization":"Testing Org","status":"approved"}	\N	2026-08-30 04:17:04	2026-08-30 04:17:04
30a48e04-3478-4028-a56d-f6f0feff8225	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	135	{"kind":"document_outcome","title":"Organization Registration \\u2014 Testing Org 2 (2026-2027) was rejected","body":"Organization Registration \\u2022 Testing Org 2","url":"\\/registrations\\/86","document_id":86,"form_type":"organization_registration","organization":"Testing Org 2","status":"rejected"}	\N	2026-08-30 04:19:52	2026-08-30 04:19:52
1c81376d-9c50-4ad9-9f0b-32f52b35a5e1	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/95","document_id":95,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:27:22	2026-09-03 08:27:22
a98c2cae-ebae-4697-aa40-884a139f9bcd	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/95","document_id":95,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:27:22	2026-09-03 08:27:22
701ceebf-6a82-4bc4-92f9-260164b6381d	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/95","document_id":95,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:27:22	2026-09-03 08:27:22
ce7741cc-d614-4af2-9f49-fd01e3642781	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/95","document_id":95,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:27:22	2026-09-03 08:27:22
f906ca65-cb48-4c5d-ac31-248199ec4b92	App\\Notifications\\ApproverProvisionedNotification	App\\Models\\User	136	{"kind":"approver_provisioned","title":"Your SDAO approver account has been created","body":"You've been added as Adviser. Check your email for your login details.","url":"\\/settings\\/security","document_id":null,"form_type":null,"organization":null,"status":null}	\N	2026-08-30 05:07:18	2026-08-30 05:07:18
021d1eae-2204-46bd-8447-0f4c21a3dba6	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Extra Curricular Testing (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Extra Curricular Testing","url":"\\/review\\/activity-calendars\\/96","document_id":96,"form_type":"activity_calendar","organization":"Extra Curricular Testing","status":null}	\N	2026-09-03 08:29:50	2026-09-03 08:29:50
3dc26925-cde4-495a-b64f-117e4f1519b8	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Extra Curricular Testing (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Extra Curricular Testing","url":"\\/review\\/activity-calendars\\/96","document_id":96,"form_type":"activity_calendar","organization":"Extra Curricular Testing","status":null}	\N	2026-09-03 08:29:50	2026-09-03 08:29:50
8892f87a-39d3-4c68-9c82-304addc17270	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Extra Curricular Testing (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Extra Curricular Testing","url":"\\/review\\/activity-calendars\\/96","document_id":96,"form_type":"activity_calendar","organization":"Extra Curricular Testing","status":null}	\N	2026-09-03 08:29:50	2026-09-03 08:29:50
f0e300d6-298d-46ae-8e15-fe31383a5a6a	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Extra Curricular Testing (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Extra Curricular Testing","url":"\\/review\\/activity-calendars\\/96","document_id":96,"form_type":"activity_calendar","organization":"Extra Curricular Testing","status":null}	\N	2026-09-03 08:29:50	2026-09-03 08:29:50
0f2a8395-cd14-4bbd-98a4-d46839b1f2fb	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 TLPH (1st Term 2026-2027)","body":"Activity Calendar \\u2022 TLPH","url":"\\/review\\/activity-calendars\\/97","document_id":97,"form_type":"activity_calendar","organization":"TLPH","status":null}	\N	2026-09-03 08:36:44	2026-09-03 08:36:44
b1a12850-7658-49f0-9485-664fa144a106	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 TLPH (1st Term 2026-2027)","body":"Activity Calendar \\u2022 TLPH","url":"\\/review\\/activity-calendars\\/97","document_id":97,"form_type":"activity_calendar","organization":"TLPH","status":null}	\N	2026-09-03 08:36:44	2026-09-03 08:36:44
2d4c0617-92ba-43cf-b587-63f5dfe67b42	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 TLPH (1st Term 2026-2027)","body":"Activity Calendar \\u2022 TLPH","url":"\\/review\\/activity-calendars\\/97","document_id":97,"form_type":"activity_calendar","organization":"TLPH","status":null}	\N	2026-09-03 08:36:44	2026-09-03 08:36:44
fa66ffd8-bf0c-48bf-95be-05a9b656a801	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 TLPH (1st Term 2026-2027)","body":"Activity Calendar \\u2022 TLPH","url":"\\/review\\/activity-calendars\\/97","document_id":97,"form_type":"activity_calendar","organization":"TLPH","status":null}	\N	2026-09-03 08:36:44	2026-09-03 08:36:44
40c368d5-6df9-46cc-a90e-25828bf3b5e4	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	139	{"kind":"document_outcome","title":"Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027) was returned for revision","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/activity-calendars\\/95","document_id":95,"form_type":"activity_calendar","organization":"Dayether's Org","status":"returned"}	\N	2026-09-03 08:38:56	2026-09-03 08:38:56
e7660dcd-e985-4fc1-b1c8-bedaf788af0d	App\\Notifications\\AccountVerifiedNotification	App\\Models\\User	137	{"kind":"account_verified","title":"Your SDAO account has been verified","body":"You can now submit documents and be bound to an organization.","url":"\\/dashboard","document_id":null,"form_type":null,"organization":null,"status":null}	\N	2026-08-30 08:33:38	2026-08-30 08:33:38
1b4ce74a-e9b5-42ef-995d-31b53eb33e2c	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Extra Curricular Testing (2026-2027)","body":"Organization Registration \\u2022 Extra Curricular Testing","url":"\\/review\\/registrations\\/87","document_id":87,"form_type":"organization_registration","organization":"Extra Curricular Testing","status":null}	\N	2026-08-30 08:37:40	2026-08-30 08:37:40
b955ff34-0aa4-4335-9a29-01d5a45292e0	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Extra Curricular Testing (2026-2027)","body":"Organization Registration \\u2022 Extra Curricular Testing","url":"\\/review\\/registrations\\/87","document_id":87,"form_type":"organization_registration","organization":"Extra Curricular Testing","status":null}	\N	2026-08-30 08:37:40	2026-08-30 08:37:40
403e0eb8-9150-46ff-99e9-c895cd20bb44	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Extra Curricular Testing (2026-2027)","body":"Organization Registration \\u2022 Extra Curricular Testing","url":"\\/review\\/registrations\\/87","document_id":87,"form_type":"organization_registration","organization":"Extra Curricular Testing","status":null}	\N	2026-08-30 08:37:40	2026-08-30 08:37:40
7d41465b-427d-4bfa-a793-417405affa8b	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Extra Curricular Testing (2026-2027)","body":"Organization Registration \\u2022 Extra Curricular Testing","url":"\\/review\\/registrations\\/87","document_id":87,"form_type":"organization_registration","organization":"Extra Curricular Testing","status":null}	\N	2026-08-30 08:37:40	2026-08-30 08:37:40
5b90c375-4eca-49f4-b9dd-39f1623018f1	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	137	{"kind":"document_outcome","title":"Organization Registration \\u2014 Extra Curricular Testing (2026-2027) was returned for revision","body":"Organization Registration \\u2022 Extra Curricular Testing","url":"\\/registrations\\/87","document_id":87,"form_type":"organization_registration","organization":"Extra Curricular Testing","status":"returned"}	\N	2026-08-30 08:43:38	2026-08-30 08:43:38
86a0a98e-ab2c-47cc-82e9-e02d9f7162af	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Extra Curricular Testing (2026-2027)","body":"Organization Registration \\u2022 Extra Curricular Testing","url":"\\/review\\/registrations\\/87","document_id":87,"form_type":"organization_registration","organization":"Extra Curricular Testing","status":null}	\N	2026-08-30 08:44:27	2026-08-30 08:44:27
bd18e5d1-1fe9-41f5-9c02-676191472f3c	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Extra Curricular Testing (2026-2027)","body":"Organization Registration \\u2022 Extra Curricular Testing","url":"\\/review\\/registrations\\/87","document_id":87,"form_type":"organization_registration","organization":"Extra Curricular Testing","status":null}	\N	2026-08-30 08:44:27	2026-08-30 08:44:27
cac66223-a746-4bf8-8bde-6c0db94fb184	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Extra Curricular Testing (2026-2027)","body":"Organization Registration \\u2022 Extra Curricular Testing","url":"\\/review\\/registrations\\/87","document_id":87,"form_type":"organization_registration","organization":"Extra Curricular Testing","status":null}	\N	2026-08-30 08:44:27	2026-08-30 08:44:27
33d84544-cd2d-400c-9241-8b89dc75b6ba	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Extra Curricular Testing (2026-2027)","body":"Organization Registration \\u2022 Extra Curricular Testing","url":"\\/review\\/registrations\\/87","document_id":87,"form_type":"organization_registration","organization":"Extra Curricular Testing","status":null}	\N	2026-08-30 08:44:27	2026-08-30 08:44:27
fff76576-817d-4294-8253-fb233df8a719	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Extra Curricular Testing (2026-2027)","body":"Organization Registration \\u2022 Extra Curricular Testing","url":"\\/review\\/registrations\\/87","document_id":87,"form_type":"organization_registration","organization":"Extra Curricular Testing","status":null}	\N	2026-08-30 08:46:43	2026-08-30 08:46:43
d3a53e28-86d0-4e1f-a2a7-bd731cbb762a	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Extra Curricular Testing (2026-2027)","body":"Organization Registration \\u2022 Extra Curricular Testing","url":"\\/review\\/registrations\\/87","document_id":87,"form_type":"organization_registration","organization":"Extra Curricular Testing","status":null}	\N	2026-08-30 08:46:43	2026-08-30 08:46:43
2730e6ed-9f66-4f0a-ae5b-7490aabf85ec	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Extra Curricular Testing (2026-2027)","body":"Organization Registration \\u2022 Extra Curricular Testing","url":"\\/review\\/registrations\\/87","document_id":87,"form_type":"organization_registration","organization":"Extra Curricular Testing","status":null}	\N	2026-08-30 08:46:43	2026-08-30 08:46:43
aeb8aed2-164b-4211-b3ae-66e0485f1ded	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Extra Curricular Testing (2026-2027)","body":"Organization Registration \\u2022 Extra Curricular Testing","url":"\\/review\\/registrations\\/87","document_id":87,"form_type":"organization_registration","organization":"Extra Curricular Testing","status":null}	\N	2026-08-30 08:46:43	2026-08-30 08:46:43
765d6dfc-b9fa-4bec-9f1b-5cbde2b63563	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Resubmitted for your review: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/95","document_id":95,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:52:01	2026-09-03 08:52:01
466e6bb2-8726-4d0b-8204-4bcf15f9f680	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Resubmitted for your review: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/95","document_id":95,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:52:01	2026-09-03 08:52:01
de880ff7-5053-47a0-98af-ebad199d1e29	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Resubmitted for your review: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/95","document_id":95,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:52:01	2026-09-03 08:52:01
655fd839-55db-4873-a55a-5e00fa3833ca	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	137	{"kind":"document_outcome","title":"Organization Registration \\u2014 Extra Curricular Testing (2026-2027) was returned for revision","body":"Organization Registration \\u2022 Extra Curricular Testing","url":"\\/registrations\\/87","document_id":87,"form_type":"organization_registration","organization":"Extra Curricular Testing","status":"returned"}	\N	2026-08-30 08:39:49	2026-08-30 08:39:49
c2a0b790-5c90-4125-9a82-8c66aaea0cf5	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Extra Curricular Testing (2026-2027)","body":"Organization Registration \\u2022 Extra Curricular Testing","url":"\\/review\\/registrations\\/87","document_id":87,"form_type":"organization_registration","organization":"Extra Curricular Testing","status":null}	\N	2026-08-30 08:42:34	2026-08-30 08:42:34
78a70ffc-e51a-4fff-a326-41775cdb3786	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Extra Curricular Testing (2026-2027)","body":"Organization Registration \\u2022 Extra Curricular Testing","url":"\\/review\\/registrations\\/87","document_id":87,"form_type":"organization_registration","organization":"Extra Curricular Testing","status":null}	\N	2026-08-30 08:42:34	2026-08-30 08:42:34
d2580bda-dbb4-4c3a-887a-88973d2bee7f	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Extra Curricular Testing (2026-2027)","body":"Organization Registration \\u2022 Extra Curricular Testing","url":"\\/review\\/registrations\\/87","document_id":87,"form_type":"organization_registration","organization":"Extra Curricular Testing","status":null}	\N	2026-08-30 08:42:34	2026-08-30 08:42:34
2c3d1382-4666-4b70-939f-8ce99c4c4375	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Extra Curricular Testing (2026-2027)","body":"Organization Registration \\u2022 Extra Curricular Testing","url":"\\/review\\/registrations\\/87","document_id":87,"form_type":"organization_registration","organization":"Extra Curricular Testing","status":null}	\N	2026-08-30 08:42:34	2026-08-30 08:42:34
c67146b1-3c41-49ad-b492-37363f727b1e	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Resubmitted for your review: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/95","document_id":95,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 08:52:01	2026-09-03 08:52:01
3bd469e0-797a-40fe-abdf-b989a541156c	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	137	{"kind":"document_outcome","title":"Organization Registration \\u2014 Extra Curricular Testing (2026-2027) was returned for revision","body":"Organization Registration \\u2022 Extra Curricular Testing","url":"\\/registrations\\/87","document_id":87,"form_type":"organization_registration","organization":"Extra Curricular Testing","status":"returned"}	\N	2026-08-30 08:46:16	2026-08-30 08:46:16
ae5b5546-1ff9-4978-9439-76e181e33f20	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	139	{"kind":"document_outcome","title":"Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027) was approved","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/activity-calendars\\/95","document_id":95,"form_type":"activity_calendar","organization":"Dayether's Org","status":"approved"}	\N	2026-09-03 08:59:03	2026-09-03 08:59:03
ee1c8429-d158-499c-8aa3-c7689ca31c76	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/98","document_id":98,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 09:01:34	2026-09-03 09:01:34
c97d1101-159d-4e37-94be-84a879028632	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/98","document_id":98,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 09:01:34	2026-09-03 09:01:34
060d37d7-7cb8-4c8b-865f-05b92046662b	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/98","document_id":98,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 09:01:34	2026-09-03 09:01:34
7a2d8090-7699-49e2-bbfe-262e9cfb2076	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/98","document_id":98,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 09:01:34	2026-09-03 09:01:34
6165c78d-7b97-416a-8771-502d15eafcb9	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Extra Curricular Testing (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Extra Curricular Testing","url":"\\/review\\/activity-calendars\\/99","document_id":99,"form_type":"activity_calendar","organization":"Extra Curricular Testing","status":null}	\N	2026-09-03 09:04:24	2026-09-03 09:04:24
fdf49bdc-74f2-4d6c-ad01-f074353b3784	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Extra Curricular Testing (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Extra Curricular Testing","url":"\\/review\\/activity-calendars\\/99","document_id":99,"form_type":"activity_calendar","organization":"Extra Curricular Testing","status":null}	\N	2026-09-03 09:04:25	2026-09-03 09:04:25
063ffd99-1863-40bc-801a-d4adae2c9883	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Extra Curricular Testing (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Extra Curricular Testing","url":"\\/review\\/activity-calendars\\/99","document_id":99,"form_type":"activity_calendar","organization":"Extra Curricular Testing","status":null}	\N	2026-09-03 09:04:25	2026-09-03 09:04:25
c774051d-aa45-40ca-84a1-3f4820d87962	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Extra Curricular Testing (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Extra Curricular Testing","url":"\\/review\\/activity-calendars\\/99","document_id":99,"form_type":"activity_calendar","organization":"Extra Curricular Testing","status":null}	\N	2026-09-03 09:04:25	2026-09-03 09:04:25
45dbcdd5-fdc4-47ec-b277-477b5464c119	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	139	{"kind":"document_outcome","title":"Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027) was approved","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/activity-calendars\\/98","document_id":98,"form_type":"activity_calendar","organization":"Dayether's Org","status":"approved"}	\N	2026-09-03 09:05:27	2026-09-03 09:05:27
4d1a147f-c942-479c-b006-e485bed51320	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	137	{"kind":"document_outcome","title":"Organization Registration \\u2014 Extra Curricular Testing (2026-2027) was approved","body":"Organization Registration \\u2022 Extra Curricular Testing","url":"\\/registrations\\/87","document_id":87,"form_type":"organization_registration","organization":"Extra Curricular Testing","status":"approved"}	\N	2026-08-30 14:35:15	2026-08-30 14:35:15
debf8ae4-78a0-48f9-a3b9-b2d9ddfa3ca6	App\\Notifications\\AccountVerifiedNotification	App\\Models\\User	138	{"kind":"account_verified","title":"Your SDAO account has been verified","body":"You can now submit documents and be bound to an organization.","url":"\\/dashboard","document_id":null,"form_type":null,"organization":null,"status":null}	\N	2026-08-30 14:39:58	2026-08-30 14:39:58
626e332c-2489-4681-9a24-300d39eee2af	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Rano El Org (2026-2027)","body":"Organization Registration \\u2022 Rano El Org","url":"\\/review\\/registrations\\/88","document_id":88,"form_type":"organization_registration","organization":"Rano El Org","status":null}	\N	2026-08-30 14:41:20	2026-08-30 14:41:20
c9d32638-7a65-4a5c-8076-6b342a08888e	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Rano El Org (2026-2027)","body":"Organization Registration \\u2022 Rano El Org","url":"\\/review\\/registrations\\/88","document_id":88,"form_type":"organization_registration","organization":"Rano El Org","status":null}	\N	2026-08-30 14:41:20	2026-08-30 14:41:20
a6ef213a-8d21-43a7-90f1-c284ce3d1c22	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Rano El Org (2026-2027)","body":"Organization Registration \\u2022 Rano El Org","url":"\\/review\\/registrations\\/88","document_id":88,"form_type":"organization_registration","organization":"Rano El Org","status":null}	\N	2026-08-30 14:41:20	2026-08-30 14:41:20
50504238-e27b-4dce-abf5-7d6b6c86fc3d	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Registration \\u2014 Rano El Org (2026-2027)","body":"Organization Registration \\u2022 Rano El Org","url":"\\/review\\/registrations\\/88","document_id":88,"form_type":"organization_registration","organization":"Rano El Org","status":null}	\N	2026-08-30 14:41:20	2026-08-30 14:41:20
2804593d-e658-4418-a68e-2c824009f7e2	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Rano El Org (2026-2027)","body":"Organization Registration \\u2022 Rano El Org","url":"\\/review\\/registrations\\/88","document_id":88,"form_type":"organization_registration","organization":"Rano El Org","status":null}	\N	2026-08-30 14:42:26	2026-08-30 14:42:26
43907d28-e479-4ecd-b041-bde4ece3d9ef	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Rano El Org (2026-2027)","body":"Organization Registration \\u2022 Rano El Org","url":"\\/review\\/registrations\\/88","document_id":88,"form_type":"organization_registration","organization":"Rano El Org","status":null}	\N	2026-08-30 14:42:27	2026-08-30 14:42:27
ed912a2b-a5c4-45ca-8cc7-a19ec3001df3	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Rano El Org (2026-2027)","body":"Organization Registration \\u2022 Rano El Org","url":"\\/review\\/registrations\\/88","document_id":88,"form_type":"organization_registration","organization":"Rano El Org","status":null}	\N	2026-08-30 14:42:27	2026-08-30 14:42:27
7217383f-0662-4b27-b2d0-96d55d01d59f	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Resubmitted for your review: Organization Registration \\u2014 Rano El Org (2026-2027)","body":"Organization Registration \\u2022 Rano El Org","url":"\\/review\\/registrations\\/88","document_id":88,"form_type":"organization_registration","organization":"Rano El Org","status":null}	\N	2026-08-30 14:42:27	2026-08-30 14:42:27
098c935e-1c10-43a5-8fc0-f720ac53e73d	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	138	{"kind":"document_outcome","title":"Organization Registration \\u2014 Rano El Org (2026-2027) was approved","body":"Organization Registration \\u2022 Rano El Org","url":"\\/registrations\\/88","document_id":88,"form_type":"organization_registration","organization":"Rano El Org","status":"approved"}	\N	2026-08-30 14:46:36	2026-08-30 14:46:36
96fee73d-1428-4de4-a62b-57f9d9b1209c	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Extra Curricular Testing (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Extra Curricular Testing","url":"\\/review\\/activity-calendars\\/100","document_id":100,"form_type":"activity_calendar","organization":"Extra Curricular Testing","status":null}	\N	2026-09-03 09:07:25	2026-09-03 09:07:25
c26c696b-ff2c-4c4a-84d2-77e8552238f3	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Extra Curricular Testing (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Extra Curricular Testing","url":"\\/review\\/activity-calendars\\/100","document_id":100,"form_type":"activity_calendar","organization":"Extra Curricular Testing","status":null}	\N	2026-09-03 09:07:25	2026-09-03 09:07:25
9cd50809-50c5-4e83-bb26-55d5f9b96016	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Extra Curricular Testing (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Extra Curricular Testing","url":"\\/review\\/activity-calendars\\/100","document_id":100,"form_type":"activity_calendar","organization":"Extra Curricular Testing","status":null}	\N	2026-09-03 09:07:25	2026-09-03 09:07:25
fce1af2d-bd80-452f-9a50-97204c87b92c	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Extra Curricular Testing (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Extra Curricular Testing","url":"\\/review\\/activity-calendars\\/100","document_id":100,"form_type":"activity_calendar","organization":"Extra Curricular Testing","status":null}	\N	2026-09-03 09:07:25	2026-09-03 09:07:25
820e6013-6ca2-4f2d-ae54-181c44645909	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/101","document_id":101,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 09:08:31	2026-09-03 09:08:31
8b233d80-0233-4673-854e-00186f3a8243	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/101","document_id":101,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 09:08:31	2026-09-03 09:08:31
ddbc5431-a96b-4b9f-ac7a-ad2455721dea	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/101","document_id":101,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 09:08:31	2026-09-03 09:08:31
92ff8e85-1bc8-4afe-a7f2-de997d770377	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	138	{"kind":"document_outcome","title":"Organization Registration \\u2014 Rano El Org (2026-2027) was returned for revision","body":"Organization Registration \\u2022 Rano El Org","url":"\\/registrations\\/88","document_id":88,"form_type":"organization_registration","organization":"Rano El Org","status":"returned"}	\N	2026-08-30 14:42:12	2026-08-30 14:42:12
04b0240a-bf1b-499a-8dd7-009616b55d14	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	138	{"kind":"document_outcome","title":"Organization Renewal \\u2014 Rano El Org (2026-2027) was approved","body":"Organization Renewal \\u2022 Rano El Org","url":"\\/renewals\\/89","document_id":89,"form_type":"organization_renewal","organization":"Rano El Org","status":"approved"}	\N	2026-08-30 14:56:27	2026-08-30 14:56:27
33e0457c-4936-42f9-bdb9-19cdbc42085b	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Calendar \\u2014 Dayether's Org (1st Term 2026-2027)","body":"Activity Calendar \\u2022 Dayether's Org","url":"\\/review\\/activity-calendars\\/101","document_id":101,"form_type":"activity_calendar","organization":"Dayether's Org","status":null}	\N	2026-09-03 09:08:31	2026-09-03 09:08:31
51040162-8b67-4d39-9cc8-d274f69c8e32	App\\Notifications\\DocumentOutcomeNotification	App\\Models\\User	137	{"kind":"document_outcome","title":"Activity Calendar \\u2014 Extra Curricular Testing (1st Term 2026-2027) was rejected","body":"Activity Calendar \\u2022 Extra Curricular Testing","url":"\\/activity-calendars\\/99","document_id":99,"form_type":"activity_calendar","organization":"Extra Curricular Testing","status":"rejected"}	\N	2026-09-03 09:08:37	2026-09-03 09:08:37
4f66d89c-0f47-4117-bc19-d2511f3e15a1	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 Rano El Org (2026-2027)","body":"Organization Renewal \\u2022 Rano El Org","url":"\\/review\\/renewals\\/89","document_id":89,"form_type":"organization_renewal","organization":"Rano El Org","status":null}	\N	2026-08-30 14:53:33	2026-08-30 14:53:33
c23f9dfd-935d-49ef-9872-a571425d4351	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 Rano El Org (2026-2027)","body":"Organization Renewal \\u2022 Rano El Org","url":"\\/review\\/renewals\\/89","document_id":89,"form_type":"organization_renewal","organization":"Rano El Org","status":null}	\N	2026-08-30 14:53:33	2026-08-30 14:53:33
a9d353ad-efd8-4c8c-925e-2b3447e77661	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 Rano El Org (2026-2027)","body":"Organization Renewal \\u2022 Rano El Org","url":"\\/review\\/renewals\\/89","document_id":89,"form_type":"organization_renewal","organization":"Rano El Org","status":null}	\N	2026-08-30 14:53:33	2026-08-30 14:53:33
9971d7a3-a4ff-4fe6-ba1d-cc1508e4c42e	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Organization Renewal \\u2014 Rano El Org (2026-2027)","body":"Organization Renewal \\u2022 Rano El Org","url":"\\/review\\/renewals\\/89","document_id":89,"form_type":"organization_renewal","organization":"Rano El Org","status":null}	\N	2026-08-30 14:53:33	2026-08-30 14:53:33
16419033-e1df-46c1-9327-28c93ee5fb1d	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	140	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Sept 9 Act (Dayether's Org)","body":"Activity Proposal \\u2022 Dayether's Org","url":"\\/review\\/activity-proposals\\/103","document_id":103,"form_type":"activity_proposal","organization":"Dayether's Org","status":null}	\N	2026-09-03 09:56:07	2026-09-03 09:56:07
e0d6cc20-574a-4461-8c55-64350808fccb	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	27	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Sept 9 Act (Dayether's Org)","body":"Activity Proposal \\u2022 Dayether's Org","url":"\\/review\\/activity-proposals\\/103","document_id":103,"form_type":"activity_proposal","organization":"Dayether's Org","status":null}	\N	2026-09-03 10:03:26	2026-09-03 10:03:26
efbb4388-b7f1-4a2c-9d80-ea374341c88a	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	41	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Sept 9 Act (Dayether's Org)","body":"Activity Proposal \\u2022 Dayether's Org","url":"\\/review\\/activity-proposals\\/103","document_id":103,"form_type":"activity_proposal","organization":"Dayether's Org","status":null}	\N	2026-09-03 10:05:48	2026-09-03 10:05:48
dc562d58-de07-49b8-a01c-39bbaa2d08b1	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	21	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Sept 9 Act (Dayether's Org)","body":"Activity Proposal \\u2022 Dayether's Org","url":"\\/review\\/activity-proposals\\/103","document_id":103,"form_type":"activity_proposal","organization":"Dayether's Org","status":null}	\N	2026-09-03 10:05:48	2026-09-03 10:05:48
5e23a082-2f5a-4b61-bc47-8a7cae5fa2bd	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	42	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Sept 9 Act (Dayether's Org)","body":"Activity Proposal \\u2022 Dayether's Org","url":"\\/review\\/activity-proposals\\/103","document_id":103,"form_type":"activity_proposal","organization":"Dayether's Org","status":null}	\N	2026-09-03 10:05:48	2026-09-03 10:05:48
74c60c31-cc4b-45f5-952c-760ab939b952	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	22	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Sept 9 Act (Dayether's Org)","body":"Activity Proposal \\u2022 Dayether's Org","url":"\\/review\\/activity-proposals\\/103","document_id":103,"form_type":"activity_proposal","organization":"Dayether's Org","status":null}	\N	2026-09-03 10:05:48	2026-09-03 10:05:48
73b582d7-e49d-4192-9c82-ba6c88498143	App\\Notifications\\ApproverHandOffNotification	App\\Models\\User	23	{"kind":"approver_hand_off","title":"Action needed: Activity Proposal \\u2014 Sept 9 Act (Dayether's Org)","body":"Activity Proposal \\u2022 Dayether's Org","url":"\\/review\\/activity-proposals\\/103","document_id":103,"form_type":"activity_proposal","organization":"Dayether's Org","status":null}	\N	2026-09-03 10:09:34	2026-09-03 10:09:34
\.


--
-- Data for Name: organization_join_requests; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.organization_join_requests (id, user_id, organization_id, status, decided_by, decided_at, decision_comment, created_at, updated_at) FROM stdin;
5	128	28	approved	121	2026-08-24 16:27:53	\N	2026-08-24 14:30:53	2026-08-24 16:27:53
\.


--
-- Data for Name: organization_memberships; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.organization_memberships (id, user_id, organization_id, "position", academic_year, is_active, created_at, updated_at) FROM stdin;
12	91	16	president	2026-2027	t	2026-08-21 05:33:51	2026-08-21 05:33:51
13	92	17	president	2026-2027	t	2026-08-21 05:35:17	2026-08-21 05:35:17
14	93	18	president	2026-2027	t	2026-08-21 05:36:43	2026-08-21 05:36:43
15	94	19	president	2026-2027	t	2026-08-21 05:38:09	2026-08-21 05:38:09
16	95	20	president	2026-2027	t	2026-08-21 05:39:36	2026-08-21 05:39:36
17	96	21	president	2026-2027	t	2026-08-21 05:41:02	2026-08-21 05:41:02
18	97	22	president	2026-2027	t	2026-08-21 05:42:29	2026-08-21 05:42:29
19	98	23	president	2026-2027	t	2026-08-21 05:43:55	2026-08-21 05:43:55
20	99	16	secretary	2026-2027	t	2026-08-21 05:44:04	2026-08-21 05:44:04
21	100	18	secretary	2026-2027	t	2026-08-21 05:44:09	2026-08-21 05:44:09
22	101	21	secretary	2026-2027	t	2026-08-21 05:44:14	2026-08-21 05:44:14
24	121	28	president	2026-2027	t	2026-08-24 06:33:16	2026-08-24 06:33:16
25	95	20	secretary	2026-2027	t	2026-08-24 16:20:33	2026-08-24 16:20:33
26	128	28	secretary	2026-2027	t	2026-08-24 16:27:53	2026-08-24 16:27:53
28	131	36	president	2026-2027	t	2026-08-30 03:56:43	2026-08-30 03:56:43
29	133	37	president	2026-2027	t	2026-08-30 04:17:05	2026-08-30 04:17:05
30	137	40	president	2026-2027	t	2026-08-30 14:35:15	2026-08-30 14:35:15
31	138	41	president	2026-2027	t	2026-08-30 14:46:36	2026-08-30 14:46:36
32	139	42	president	2026-2027	t	2026-09-03 08:07:09	2026-09-03 08:07:09
33	141	43	president	2026-2027	t	2026-09-05 13:41:50	2026-09-05 13:41:50
\.


--
-- Data for Name: organization_registration_details; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.organization_registration_details (id, document_id, organization_type, purpose_of_organization, contact_person, contact_no, email_address, date_organized, adviser_id, created_at, updated_at, academic_year, term, covers_academic_year) FROM stdin;
18	35	co_curricular	Founded to serve the college coders' and computer science enthusiasts' organization of NU Lipa.	Miguel Torres	09173315488	torresm@students.nu-lipa.edu.ph	2024-08-13	48	2026-08-21 05:32:36	2026-08-21 05:32:36	2026-2027	first_term	2026-2027
19	36	co_curricular	Founded to serve the United Architects of the Philippines Student Auxiliary chapter of NU Lipa.	Isabelle Reyes	09179870180	reyesi@students.nu-lipa.edu.ph	2024-08-23	51	2026-08-21 05:34:02	2026-08-21 05:34:02	2026-2027	first_term	2026-2027
20	37	co_curricular	Founded to serve the Philippine Institute of Civil Engineers student chapter of NU Lipa.	Nathaniel Cruz	09178353199	cruzn@students.nu-lipa.edu.ph	2024-08-05	85	2026-08-21 05:35:28	2026-08-21 05:35:28	2026-2027	first_term	2026-2027
21	38	co_curricular	Founded to serve the BS Psychology student organization of NU Lipa.	Samantha Diaz	09173579356	diazs@students.nu-lipa.edu.ph	2024-08-01	86	2026-08-21 05:36:54	2026-08-21 05:36:54	2026-2027	first_term	2026-2027
22	39	co_curricular	Founded to serve the Medical Technology Student Council of NU Lipa.	Kevin Mendoza	09178653055	mendozak@students.nu-lipa.edu.ph	2024-08-21	87	2026-08-21 05:38:20	2026-08-21 05:38:20	2026-2027	first_term	2026-2027
23	40	co_curricular	Founded to serve the Junior Philippine Institute of Accountants chapter of NU Lipa.	Andrea Villareal	09176534969	villarreala@students.nu-lipa.edu.ph	2024-08-23	40	2026-08-21 05:39:47	2026-08-21 05:39:47	2026-2027	first_term	2026-2027
24	41	extra_curricular	Founded to serve the campus Red Cross Youth volunteer corps of NU Lipa.	Patricia Gomez	09174767378	gomezp@students.nu-lipa.edu.ph	2024-08-19	88	2026-08-21 05:41:13	2026-08-21 05:41:13	2026-2027	first_term	2026-2027
25	42	extra_curricular	Founded to serve the Senior High School competitive esports club of NU Lipa.	Joshua Ramos	09178908756	ramosj@students.nu-lipa.edu.ph	2024-08-07	54	2026-08-21 05:42:40	2026-08-21 05:42:40	2026-2027	first_term	2026-2027
26	43	extra_curricular	Draft organization_registration for G17, not yet submitted.	Ella Marquez	09178845113	marqueze@students.nu-lipa.edu.ph	2024-08-15	51	2026-08-21 05:44:22	2026-08-21 05:44:22	2026-2027	first_term	2026-2027
27	44	extra_curricular	A cross-program networking and leadership org for NU Lipa students.	Francis Ocampo	09177686863	ocampof@students.nu-lipa.edu.ph	2025-01-15	89	2026-08-21 05:44:27	2026-08-21 05:44:27	2026-2027	first_term	2026-2027
28	45	co_curricular	Community extension and outreach programs for NU Lipa student organizations.	Grace Lim	09175906586	limg@students.nu-lipa.edu.ph	2025-02-10	48	2026-08-21 05:45:23	2026-08-21 05:45:23	2026-2027	first_term	2026-2027
29	46	extra_curricular	A creatives and multimedia arts collective.	Harold Navarro	09171259775	navarroh@students.nu-lipa.edu.ph	2025-03-01	51	2026-08-21 05:46:20	2026-08-21 05:46:20	2026-2027	first_term	2026-2027
30	47	co_curricular	Draft organization_renewal for CODECS, not yet submitted.	Miguel Torres	09171877976	torresm@students.nu-lipa.edu.ph	2024-08-15	48	2026-08-21 05:47:16	2026-08-21 05:47:16	2026-2027	first_term	2027-2028
31	48	co_curricular	Renewing UAPSA's recognition for the current academic year.	UAPSA Officers	09179377062	uapsa@students.nu-lipa.edu.ph	2024-08-15	51	2026-08-21 05:47:25	2026-08-21 05:47:25	2026-2027	first_term	2027-2028
32	49	co_curricular	Renewing PICE's recognition for the current academic year.	PICE Officers	09174677944	pice@students.nu-lipa.edu.ph	2024-08-15	85	2026-08-21 05:48:25	2026-08-21 05:48:25	2026-2027	first_term	2027-2028
33	50	co_curricular	Renewing JPIA's recognition for the current academic year.	JPIA Officers	09171236269	jpia@students.nu-lipa.edu.ph	2024-08-15	40	2026-08-21 05:49:33	2026-08-21 05:49:33	2026-2027	first_term	2027-2028
34	51	co_curricular	Renewing Red Cross Youth's recognition for the current academic year.	Red Cross Youth Officers	09171374096	redcrossyouth@students.nu-lipa.edu.ph	2024-08-15	88	2026-08-21 05:50:53	2026-08-21 05:50:53	2026-2027	first_term	2027-2028
35	71	extra_curricular	Bring cosplay culture in our school.	Benedict James Tan	09610555193	tanbm@students.nu-lipa.edu.ph	2026-07-30	122	2026-08-24 05:50:22	2026-08-24 06:16:16	2026-2027	first_term	2026-2027
36	72	extra_curricular	Founded to serve the campus Red Cross Youth volunteer corps of NU Lipa.	Patricia Gomez	09174767378	gomezp@students.nu-lipa.edu.ph	2024-08-19	88	2026-08-24 06:29:47	2026-08-24 06:29:47	2026-2027	first_term	2027-2028
37	73	extra_curricular	The purpose of the organization is to bring together VALORANT players who share a passion for competitive gaming, teamwork, and personal improvement. It aims to provide a positive community where members can develop their skills, improve communication and strategy, participate in tournaments, and build good sportsmanship. The organization also seeks to promote responsible gaming, friendship, and collaboration among its members.	Manjean Faldas	09552301921	mingchancutie@gmail.com	2026-08-24	126	2026-08-24 08:48:56	2026-08-24 08:48:56	2026-2027	first_term	2026-2027
38	74	extra_curricular	The purpose of the organization is to bring together VALORANT players who share a passion for competitive gaming, teamwork, and personal improvement. It aims to provide a positive community where members can develop their skills, improve communication and strategy, participate in tournaments, and build good sportsmanship. The organization also seeks to promote responsible gaming, friendship, and collaboration among its members.\r\n\r\nIn addition, the organization aims to create opportunities for members to gain experience in leadership, event management, and team coordination through organized matches, training sessions, and tournaments. It encourages members to support one another, learn from both victories and losses, and maintain respect toward teammates and opponents. Through these activities, the organization hopes to build a strong and active gaming community where members can enjoy VALORANT while developing useful skills that can also be applied outside of gaming.	Manjean Faldas	09571217921	mingchancutie@gmail.com	2026-08-24	126	2026-08-24 09:12:04	2026-08-24 09:27:52	2026-2027	first_term	2026-2027
39	82	co_curricular	DOTA GMAE	Kuku Palad	0912 123 0239	kukupalad@students.nu-lipa.edu.ph	2026-08-29	126	2026-08-29 14:15:40	2026-08-29 14:15:40	2026-2027	first_term	2026-2027
40	83	co_curricular	asd	Jaypee Dela Cruz	0912 123 0239	jaypee@gmail.com	2026-08-30	132	2026-08-30 02:54:04	2026-08-30 02:54:04	2026-2027	first_term	2026-2027
41	84	co_curricular	MLBB Org para sa mga emel players	Dad Jaypee Dela Cruz	09121230213	daddyjaypee@gmail.com	2026-08-30	132	2026-08-30 03:46:42	2026-08-30 03:51:11	2026-2027	first_term	2026-2027
42	85	co_curricular	asd	BOSSDOGIE	09121230212	mingchancutie@gmail.com	2026-08-30	134	2026-08-30 04:13:47	2026-08-30 04:13:47	2026-2027	first_term	2026-2027
43	86	co_curricular	asdasdawd	BOSSDOGIE	09552301921	jaypee@gmail.com	2026-08-30	134	2026-08-30 04:16:08	2026-08-30 04:16:08	2026-2027	first_term	2026-2027
44	87	extra_curricular	purpose of organization	Manjean Faldas	09827129812	jaypee@gmail.com	2026-08-30	136	2026-08-30 08:37:37	2026-08-30 08:46:43	2026-2027	first_term	2026-2027
45	88	extra_curricular	a	b	09121230212	marvin.atanacio@nu-lipa.edu.ph	2026-08-30	123	2026-08-30 14:41:16	2026-08-30 14:42:26	2026-2027	first_term	2026-2027
46	89	extra_curricular	a	b	09121230212	marvin.atanacio@nu-lipa.edu.ph	2026-08-30	123	2026-08-30 14:53:31	2026-08-30 14:53:31	2026-2027	first_term	2027-2028
47	90	co_curricular	haha	Marvin Atanacio	09121230212	marvin.atanacio@nu-lipa.edu.ph	2026-09-03	140	2026-09-03 08:06:36	2026-09-03 08:06:36	2026-2027	first_term	2026-2027
48	91	co_curricular	haha	Marvin Atanacio	09121230212	marvin.atanacio@nu-lipa.edu.ph	2026-09-03	140	2026-09-03 08:08:01	2026-09-03 08:08:01	2026-2027	first_term	2027-2028
49	92	co_curricular	haha	Marvin Atanacio	09121230212	marvin.atanacio@nu-lipa.edu.ph	2026-09-03	140	2026-09-03 08:10:21	2026-09-03 08:10:21	2026-2027	first_term	2027-2028
50	104	co_curricular	asd	Kuku Palad	09371721823	jaypee@gmaill.com	2026-09-05	142	2026-09-05 13:39:54	2026-09-05 13:41:50	2026-2027	first_term	2026-2027
\.


--
-- Data for Name: organizations; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.organizations (id, name, school_id, program_id, created_at, updated_at) FROM stdin;
16	CODECS	6	12	2026-08-21 05:32:34	2026-08-21 05:32:34
17	UAPSA	6	15	2026-08-21 05:34:00	2026-08-21 05:34:00
18	PICE	6	14	2026-08-21 05:35:26	2026-08-21 05:35:26
19	Psychology Org	7	17	2026-08-21 05:36:52	2026-08-21 05:36:52
20	MTSC	7	18	2026-08-21 05:38:18	2026-08-21 05:38:18
21	JPIA	8	21	2026-08-21 05:39:45	2026-08-21 05:39:45
22	Red Cross Youth	7	\N	2026-08-21 05:41:11	2026-08-21 05:41:11
23	Venaris Esports	5	\N	2026-08-21 05:42:38	2026-08-21 05:42:38
24	G17	6	13	2026-08-21 05:44:20	2026-08-21 05:44:20
25	NEXUS	6	13	2026-08-21 05:44:25	2026-08-21 05:44:25
26	COMEX	8	20	2026-08-21 05:45:21	2026-08-21 05:45:21
27	CREA8ives	8	20	2026-08-21 05:46:19	2026-08-21 05:46:19
28	Cosplayers	6	13	2026-08-24 05:50:22	2026-08-24 05:50:22
29	Valorant Club	6	13	2026-08-24 08:48:55	2026-08-24 08:48:55
30	Valorant Club	6	13	2026-08-24 09:12:04	2026-08-24 09:12:04
31	Dota Club	6	13	2026-08-29 14:15:40	2026-08-29 14:15:40
35	Team Liquid PH	7	17	2026-08-30 02:54:04	2026-08-30 02:54:04
36	TLPH	8	19	2026-08-30 03:46:42	2026-08-30 03:46:42
37	Testing Org	7	18	2026-08-30 04:13:47	2026-08-30 04:13:47
38	Testing Org 2	8	19	2026-08-30 04:16:08	2026-08-30 04:16:08
40	Extra Curricular Testing	\N	\N	2026-08-30 08:37:37	2026-08-30 08:37:37
41	Rano El Org	\N	\N	2026-08-30 14:41:16	2026-08-30 14:41:16
42	Dayether's Org	6	13	2026-09-03 08:06:36	2026-09-03 08:06:36
43	Gen Org	8	21	2026-09-05 13:39:54	2026-09-05 13:39:54
\.


--
-- Data for Name: passkeys; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.passkeys (id, user_id, name, credential_id, credential, last_used_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.password_reset_tokens (email, token, created_at) FROM stdin;
ereny@nu-lipa.edu.ph	$2y$12$0a/9OE/e2E7549mLpg8ueeMQjOLUKiuPNi8jx.cZK3Jje0v6eEGYe	2026-08-24 06:10:28
bloodt@nu-lipa.edu.ph	$2y$12$4ploHSzXhzavjZVq3UK2EeJZKC5VsI2h1upXS3qE7npHfPW/P.UIC	2026-08-24 06:20:56
shinboo@nu-lipa.edu.ph	$2y$12$42hPnVTHWKfjftRs3n8d6Ob8xu/ptRuEUqFKhXJi2GBfywQxa5Hru	2026-08-24 08:38:56
\.


--
-- Data for Name: programs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.programs (id, school_id, name, created_at, updated_at) FROM stdin;
12	6	BS Computer Science	2026-08-19 14:49:05	2026-08-19 14:49:05
13	6	BS Information Technology	2026-08-19 14:49:07	2026-08-19 14:49:07
14	6	BS Civil Engineering	2026-08-19 14:49:10	2026-08-19 14:49:10
15	6	BS Architecture	2026-08-19 14:49:13	2026-08-19 14:49:13
16	7	BS Nursing	2026-08-19 14:49:18	2026-08-19 14:49:18
17	7	BS Psychology	2026-08-19 14:49:20	2026-08-19 14:49:20
18	7	Medical Technology	2026-08-19 14:49:23	2026-08-19 14:49:23
19	8	BS Business Administration (Financial Management)	2026-08-19 14:49:28	2026-08-19 14:49:28
20	8	BS Business Administration (Marketing Management)	2026-08-19 14:49:30	2026-08-19 14:49:30
21	8	BS Accountancy	2026-08-19 14:49:32	2026-08-19 14:49:32
22	8	BS Tourism Management	2026-08-19 14:49:34	2026-08-19 14:49:34
23	9	BS Computer Science	2026-08-21 04:46:57	2026-08-21 04:46:57
24	9	BS Information Technology	2026-08-21 04:47:04	2026-08-21 04:47:04
\.


--
-- Data for Name: role_assignments; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.role_assignments (id, user_id, role, school_id, program_id, organization_id, created_at, updated_at) FROM stdin;
21	21	sdao_member	\N	\N	\N	2026-08-19 14:48:52	2026-08-19 14:48:52
22	22	sdao_member	\N	\N	\N	2026-08-19 14:48:53	2026-08-19 14:48:53
23	23	assistant_director_academic_services	\N	\N	\N	2026-08-19 14:48:55	2026-08-19 14:48:55
24	24	academic_director	\N	\N	\N	2026-08-19 14:48:57	2026-08-19 14:48:57
25	25	executive_director	\N	\N	\N	2026-08-19 14:48:59	2026-08-19 14:48:59
26	26	principal	5	\N	\N	2026-08-19 14:49:01	2026-08-19 14:49:01
27	27	dean	6	\N	\N	2026-08-19 14:49:04	2026-08-19 14:49:04
28	28	program_chair	\N	12	\N	2026-08-19 14:49:06	2026-08-19 14:49:06
29	29	program_chair	\N	13	\N	2026-08-19 14:49:09	2026-08-19 14:49:09
30	30	program_chair	\N	14	\N	2026-08-19 14:49:12	2026-08-19 14:49:12
31	31	program_chair	\N	15	\N	2026-08-19 14:49:14	2026-08-19 14:49:14
32	32	dean	7	\N	\N	2026-08-19 14:49:17	2026-08-19 14:49:17
33	33	program_chair	\N	16	\N	2026-08-19 14:49:20	2026-08-19 14:49:20
34	34	program_chair	\N	17	\N	2026-08-19 14:49:22	2026-08-19 14:49:22
35	36	dean	8	\N	\N	2026-08-19 14:49:27	2026-08-19 14:49:27
36	37	program_chair	\N	19	\N	2026-08-19 14:49:29	2026-08-19 14:49:29
37	37	program_chair	\N	20	\N	2026-08-19 14:49:31	2026-08-19 14:49:31
38	38	program_chair	\N	21	\N	2026-08-19 14:49:34	2026-08-19 14:49:34
39	39	program_chair	\N	22	\N	2026-08-19 14:49:36	2026-08-19 14:49:36
41	41	sdao_member	\N	\N	\N	2026-08-21 04:46:51	2026-08-21 04:46:51
42	42	sdao_member	\N	\N	\N	2026-08-21 04:46:52	2026-08-21 04:46:52
43	43	assistant_director_academic_services	\N	\N	\N	2026-08-21 04:46:53	2026-08-21 04:46:53
44	44	academic_director	\N	\N	\N	2026-08-21 04:46:53	2026-08-21 04:46:53
45	45	executive_director	\N	\N	\N	2026-08-21 04:46:54	2026-08-21 04:46:54
46	46	dean	9	\N	\N	2026-08-21 04:46:57	2026-08-21 04:46:57
47	47	program_chair	\N	23	\N	2026-08-21 04:46:59	2026-08-21 04:46:59
50	50	program_chair	\N	24	\N	2026-08-21 04:47:06	2026-08-21 04:47:06
49	49	student	\N	\N	\N	2026-08-21 04:47:03	2026-08-21 04:47:03
52	52	student	\N	\N	\N	2026-08-21 04:47:10	2026-08-21 04:47:10
55	55	student	\N	\N	\N	2026-08-21 04:47:18	2026-08-21 04:47:18
66	89	adviser	\N	\N	\N	2026-08-21 05:31:52	2026-08-21 05:31:52
67	90	program_chair	\N	18	\N	2026-08-21 05:31:54	2026-08-21 05:31:54
48	48	adviser	\N	\N	16	2026-08-21 04:47:02	2026-08-21 05:33:50
51	51	adviser	\N	\N	17	2026-08-21 04:47:08	2026-08-21 05:35:16
62	85	adviser	\N	\N	18	2026-08-21 05:31:48	2026-08-21 05:36:42
63	86	adviser	\N	\N	19	2026-08-21 05:31:49	2026-08-21 05:38:08
64	87	adviser	\N	\N	20	2026-08-21 05:31:50	2026-08-21 05:39:35
40	40	adviser	\N	\N	21	2026-08-19 14:49:38	2026-08-21 05:41:02
65	88	adviser	\N	\N	22	2026-08-21 05:31:51	2026-08-21 05:42:28
54	54	adviser	\N	\N	23	2026-08-21 04:47:16	2026-08-21 05:43:55
68	122	adviser	\N	\N	28	2026-08-24 06:10:27	2026-08-24 06:33:16
70	126	adviser	\N	\N	31	2026-08-24 08:38:54	2026-08-29 14:20:21
72	132	adviser	\N	\N	36	2026-08-30 02:41:30	2026-08-30 03:56:43
73	134	adviser	\N	\N	37	2026-08-30 04:12:54	2026-08-30 04:17:05
74	136	adviser	\N	\N	40	2026-08-30 05:07:18	2026-08-30 14:35:15
69	123	adviser	\N	\N	41	2026-08-24 06:20:54	2026-08-30 14:46:36
75	140	adviser	\N	\N	42	2026-09-03 08:05:20	2026-09-03 08:07:09
76	142	adviser	\N	\N	43	2026-09-05 13:36:45	2026-09-05 13:41:50
\.


--
-- Data for Name: schools; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.schools (id, name, type, created_at, updated_at) FROM stdin;
5	Senior High School	senior_high	2026-08-19 14:48:59	2026-08-19 14:48:59
6	School of Architecture, Computing, and Engineering	regular	2026-08-19 14:49:02	2026-08-19 14:49:02
7	School of Allied Health and Sciences	regular	2026-08-19 14:49:15	2026-08-19 14:49:15
8	School of Accountancy, Business, and Management	regular	2026-08-19 14:49:25	2026-08-19 14:49:25
9	School of Computing and IT	regular	2026-08-21 04:46:55	2026-08-21 04:46:55
10	School of Business and Accountancy	regular	2026-08-21 04:47:11	2026-08-21 04:47:11
11	School of Health Sciences	regular	2026-08-21 04:47:11	2026-08-21 04:47:11
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
1lcXFdW1VKssAHYp8RH9bHP4SAxOmZYCBJYUeOMM	\N	127.0.0.1	curl/8.12.1	eyJfdG9rZW4iOiJzUmlHZEI5anZZU2FHTG1hRFpRYlBSU3lMb1Ixa0lpYU5zNEFzQXRwIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9sb2dpbiIsInJvdXRlIjoibG9naW4ifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==	1783930329
lxPH2fLqVQPfmeGRTtXaCeOlkanC5PKwyf40mbZS	\N	127.0.0.1	curl/8.12.1	eyJfdG9rZW4iOiJWRTF0dDk1MGlXRmx2VmJDUUVuOHVZam1SVmVyaXN4NGZtY2tQMHV1IiwidXJsIjp7ImludGVuZGVkIjoiaHR0cDpcL1wvMTI3LjAuMC4xOjgwMDBcL2Rhc2hib2FyZCJ9LCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wvMTI3LjAuMC4xOjgwMDBcL2Rhc2hib2FyZCIsInJvdXRlIjoiZGFzaGJvYXJkIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=	1783930352
5Xo5UazU7PZED7in7tC0hcuab0BMmTBTIvX59a2h	\N	127.0.0.1	curl/8.12.1	eyJfdG9rZW4iOiJ6dmMyRFdROTNPZUc1UWpXeXdoanRTZ0cwRG1rbVR2VE1MdEJ4WmtQIiwidXJsIjp7ImludGVuZGVkIjoiaHR0cDpcL1wvMTI3LjAuMC4xOjgwMDBcL3NldHRpbmdzXC9wcm9maWxlIn0sIl9wcmV2aW91cyI6eyJ1cmwiOiJodHRwOlwvXC8xMjcuMC4wLjE6ODAwMFwvc2V0dGluZ3NcL3Byb2ZpbGUiLCJyb3V0ZSI6InByb2ZpbGUuZWRpdCJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19	1783930354
rL1nSH37dLDi6oRgguJPMPIO7oNu3Y7tEjL9Q8OB	\N	127.0.0.1	curl/8.12.1	eyJfdG9rZW4iOiJxTlRJMnIxa1Jzc3dvNnJ5WEVobG51ZzlNOThBRTlhYjhrYVZjWE1HIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9yZWdpc3RlciIsInJvdXRlIjoicmVnaXN0ZXIifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==	1783930357
JWD55HuYWMQOAwJOUGU6r6Zd2130fg8d4aSMIy6S	\N	127.0.0.1	curl/8.12.1	eyJfdG9rZW4iOiJJZlI3MTNSWFhiRU1WcTFFZ1ZSNzc3ZzQyRFZNRGhINFVXS1hBcWFwIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9mb3Jnb3QtcGFzc3dvcmQiLCJyb3V0ZSI6InBhc3N3b3JkLnJlcXVlc3QifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==	1783930360
Cj4HjzvXqlTUlBZQQICv8uEompXKGQzyhxlXhi7p	1	127.0.0.1	curl/8.12.1	eyJfdG9rZW4iOiJMSlg0WG9PNjA4VEJtZXFDdU5FR1lpYm52MFZRM0h3ZzY1aGxQVmNXIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9zZXR0aW5nc1wvcHJvZmlsZSIsInJvdXRlIjoicHJvZmlsZS5lZGl0In0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfSwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjF9	1783930454
3tbWQ7hvoVdTwdemx0AtQzqhxsqeq3mzkDIuxsIq	1	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36	eyJfdG9rZW4iOiJZN2RxWlU2WFFWQ3BWNUQxQTVaOTJkUUZlMDhxMXZmZ0dvOU85aVJ5IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDAwXC9sb2dpbiIsInJvdXRlIjoibG9naW4ifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI6MX0=	1783932433
tC1idE3wz1W3d4WRGgBslXBk9vZE9cJAifTML34V	1	127.0.0.1	curl/8.12.1	eyJfdG9rZW4iOiJwZDJyRFloNk1UOEhIaTE5dHlQeG42aDNac1d0eDhOMW1XaDRyeTZVIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9kYXNoYm9hcmQiLCJyb3V0ZSI6ImRhc2hib2FyZCJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX0sImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjoxfQ==	1783940874
saGDwjAUVDUBOVK81Aubud4fs0QKqEcWxfYhKOTd	1	127.0.0.1	curl/8.12.1	eyJfdG9rZW4iOiJkQWdEM1lJelN6cWRmQ0t0U3laNEF5bDU5WnlUNnF0V3lyaTBvWm5lIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9kYXNoYm9hcmQiLCJyb3V0ZSI6ImRhc2hib2FyZCJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX0sImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjoxfQ==	1783941159
0yZ1KIysWPhagICBhxTp3CYMN6aQc2NDJHBRTDfK	1	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36	eyJfdG9rZW4iOiJxa29NQ08xcjZjTUhuTGFKZEhkNDdKWUFNRjA2MUJFY2lMNzU0UDlPIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9yZXZpZXdcL2FjdGl2aXR5LWNhbGVuZGFycyIsInJvdXRlIjoicmV2aWV3LmFjdGl2aXR5LWNhbGVuZGFycy5pbmRleCJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX0sImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjoxfQ==	1783930846
\.


--
-- Data for Name: settings; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.settings (id, key, value, created_at, updated_at) FROM stdin;
2	current_period	2026-2027:first_term	\N	2026-09-05 12:34:23
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at, account_status, id_number) FROM stdin;
126	Shin Boo	shinboo@nu-lipa.edu.ph	2026-08-24 08:38:54	$2y$12$ZsH9wTUrVm5Zw1V4kEPtoeeXhd.hw32FhyFZ8wKnOkOCApAjoCinG	\N	2026-08-24 08:38:54	2026-08-24 08:38:54	\N	\N	\N	verified	\N
34	Ms. Diane Angelika Nicole D. Novicio	noviciodd@nu-lipa.edu.ph	2026-08-19 14:49:21	$2y$12$fRs8bazNVFl2LxX92pvaPO/fWk2WuRhpubvkqTC8PekZqGQnjzz4i	0c2mYXwAm5DYsGGZpia4xU4fqTMsD9heOwyHzYUOv5k0gVcmJBTAFEk1uLpW	2026-08-19 14:49:21	2026-08-21 04:42:38	\N	\N	\N	verified	\N
123	Blood Test	bloodt@nu-lipa.edu.ph	2026-08-24 06:20:54	$2y$12$3yBwqcRQJLmZt3eXymkDDukMSmpn/D4.3Dp82MejaJGa4G.JpcqIy	\N	2026-08-24 06:20:54	2026-08-24 06:20:54	\N	\N	\N	verified	\N
30	Engr. Emmanuel P. Maala	maalaep@nu-lipa.edu.ph	2026-08-19 14:49:11	$2y$12$A1MHAlemksgMMA6/fJJTB.wZT.h6eujpU5tCMLmgYblaPVcSeEFP.	ywWf4l8RBA3jLy6VbC2tQzq1Y9TmI64hpBtrwxag1kv96s5rlICJWDD4Yi1R	2026-08-19 14:49:11	2026-08-21 04:42:27	\N	\N	\N	verified	\N
38	Engr. Rosa Maria C. Cayabyab	cayabyabrc@nu-lipa.edu.ph	2026-08-19 14:49:33	$2y$12$iIIYeCtK4S30pYDauoppNubGvuD6Qjvetmq19bvY6F5ngHW2s9RpS	b7IAF4M64CsdY8qCg3rvg3YI184HtRFpJhiyg51kY6KLtaaJpCcUnnyOHzGN	2026-08-19 14:49:33	2026-08-21 04:42:50	\N	\N	\N	verified	\N
36	Jay-Ar C. Dimaculangan	dimaculanganjc@nu-lipa.edu.ph	2026-08-19 14:49:26	$2y$12$0Odw6raiHC/sPkmSgHURKuMvEGKozz2npGnf4mUFhv2TZf13ipZLi	nn1J774ANX1fQ93EjYgLCkIg2SqHtDPggyhTjLnAxj4MM4tMNB1hEG0rfwTk	2026-08-19 14:49:26	2026-08-21 04:42:43	\N	\N	\N	verified	\N
22	Zaira Joy Enayo	enayoz@nu-lipa.edu.ph	2026-08-19 14:48:51	$2y$12$dFHSnwdoVsziUjWJuWOWr.4FTDr9EDGhwGDYXvAOJptH34xz0/cWS	NoxB2yvuUZH7LofDaG0l5uZ7pQcS60YtUg3WjRRs85Qz5angQt51FRHvOY2N	2026-08-19 14:48:51	2026-08-21 04:42:06	\N	\N	\N	verified	\N
26	Erna Rosario	rosarioe@nu-lipa.edu.ph	2026-08-19 14:49:00	$2y$12$/jTirXTdInNK8TslbymuzuGoPeWZmwG3nq45Pu/PM8BRH9Mv0nb5K	0oKcg3c5nm	2026-08-19 14:49:00	2026-08-21 04:42:15	\N	\N	\N	verified	\N
28	Dr. Alice Lacorte	lacortea@nu-lipa.edu.ph	2026-08-19 14:49:06	$2y$12$7vlXCXoYsb2xfNHhk9o75e4NVkJ7HWjqzjyMMlkzqJ0pX34dIAAUm	hjOETBgRuq	2026-08-19 14:49:06	2026-08-21 04:42:21	\N	\N	\N	verified	\N
31	Ar. Ryan Panapanaan	panapanaanr@nu-lipa.edu.ph	2026-08-19 14:49:13	$2y$12$gdscU5wlTs2WqnF9Z4hUwO/KUXIKOsfzvMIjWWtORzP4lA0aw2m5m	oDsfRwX1gp	2026-08-19 14:49:13	2026-08-21 04:42:29	\N	\N	\N	verified	\N
32	Maria Lourdes C. Bañaga	banagamc@nu-lipa.edu.ph	2026-08-19 14:49:16	$2y$12$mnl7P/XsWHcSOpaBrYlWVOBAH37kC7aJJIwsE.gDK8Ed683Pymi/a	KJzb4Nr54i	2026-08-19 14:49:16	2026-08-21 04:42:32	\N	\N	\N	verified	\N
33	Dr. Maria Andrea M. Magaling	magalingmm@nu-lipa.edu.ph	2026-08-19 14:49:19	$2y$12$NMTaO3h32NgdPznDrtEEMeYCj7OS7DrPPI7sdE0C/RdfYA5OpKzjO	OHDxg9YD3Q	2026-08-19 14:49:19	2026-08-21 04:42:35	\N	\N	\N	verified	\N
35	Maria Dolores C. Evangelista (Associate Dean, Medical Technology)	evangelistamc@nu-lipa.edu.ph	2026-08-19 14:49:24	$2y$12$FOmogQwOaNqJJ0TYCnlFYOCNttVgjkTKNn.Wy5MKELdRGK3etxguK	RNRhUq3o5S	2026-08-19 14:49:24	2026-08-21 04:42:41	\N	\N	\N	verified	\N
37	Dr. Ronald Catapang	catapangr@nu-lipa.edu.ph	2026-08-19 14:49:28	$2y$12$ELaPkHB4VleY5oLusRiE4uNTFKQq1feO0jpq8ETacaEEcylio5sNC	e2W7Nd53ko	2026-08-19 14:49:28	2026-08-21 04:42:45	\N	\N	\N	verified	\N
39	Dr. Gene Roy P. Hernandez	hernandezgp@nu-lipa.edu.ph	2026-08-19 14:49:35	$2y$12$rVSK0j8.k9S8C9VevqLTPu/OZJJJQ2PImp/t9oj.NXa0M5MZItjdO	BV3E74QKBxg7sYKiUAME3L35mqHTsvWVifVnc1MaZ1oXN0l5yk6ajxktShVW	2026-08-19 14:49:35	2026-08-21 04:42:52	\N	\N	\N	verified	\N
48	Adviser One	adviser-one@nu-lipa.edu.ph	2026-08-21 04:47:00	$2y$12$BkjnrUpWeuh9Fe4K92pCqu9RB06OwgdT9Jb/JI0tZHaAzn4c2IdFK	C5FfQcohGNoChttJb1rdYVSO0KgWN10JswwjKSReuNiIppBRlwEQUso127n1	2026-08-21 04:47:00	2026-08-24 04:20:33	\N	\N	\N	verified	\N
44	Academic Director	academic-director@nu-lipa.edu.ph	2026-08-21 04:46:49	$2y$12$BkjnrUpWeuh9Fe4K92pCqu9RB06OwgdT9Jb/JI0tZHaAzn4c2IdFK	cRYhG95gQ9	2026-08-21 04:46:49	2026-08-24 04:20:27	\N	\N	\N	verified	\N
45	Executive Director	executive-director@nu-lipa.edu.ph	2026-08-21 04:46:50	$2y$12$BkjnrUpWeuh9Fe4K92pCqu9RB06OwgdT9Jb/JI0tZHaAzn4c2IdFK	kNYhYwQeDm	2026-08-21 04:46:50	2026-08-24 04:20:28	\N	\N	\N	verified	\N
49	Student Alpha	student-alpha@students.nu-lipa.edu.ph	2026-08-21 04:47:02	$2y$12$BkjnrUpWeuh9Fe4K92pCqu9RB06OwgdT9Jb/JI0tZHaAzn4c2IdFK	2e0U0IYv4h	2026-08-21 04:47:02	2026-08-24 04:20:34	\N	\N	\N	verified	\N
51	Adviser Two	adviser-two@nu-lipa.edu.ph	2026-08-21 04:47:06	$2y$12$BkjnrUpWeuh9Fe4K92pCqu9RB06OwgdT9Jb/JI0tZHaAzn4c2IdFK	BMZMEtQu2n	2026-08-21 04:47:06	2026-08-24 04:20:37	\N	\N	\N	verified	\N
52	Student Beta	student-beta@students.nu-lipa.edu.ph	2026-08-21 04:47:09	$2y$12$BkjnrUpWeuh9Fe4K92pCqu9RB06OwgdT9Jb/JI0tZHaAzn4c2IdFK	UUrjguB6Cw	2026-08-21 04:47:09	2026-08-24 04:20:39	\N	\N	\N	verified	\N
54	Adviser SHS	adviser-shs@nu-lipa.edu.ph	2026-08-21 04:47:15	$2y$12$BkjnrUpWeuh9Fe4K92pCqu9RB06OwgdT9Jb/JI0tZHaAzn4c2IdFK	stY5qZhMVF	2026-08-21 04:47:15	2026-08-24 04:20:42	\N	\N	\N	verified	\N
55	Student Gamma	student-gamma@students.nu-lipa.edu.ph	2026-08-21 04:47:17	$2y$12$BkjnrUpWeuh9Fe4K92pCqu9RB06OwgdT9Jb/JI0tZHaAzn4c2IdFK	7GV3XKEBzU	2026-08-21 04:47:17	2026-08-24 04:20:43	\N	\N	\N	verified	\N
40	Marvin Atanacio	atanaciom@nu-lipa.edu.ph	2026-08-19 14:49:37	$2y$12$PgqdE39V9YQxXIgttbdxzekHetBudn9r9CXaJM/y.lKhllADH5GH.	FsPXT4kZvktkTETAfur6v8Iav6SjzWGKLULcbdmHINVR4CZZw9cyAoFAOx3a	2026-08-19 14:49:37	2026-08-21 04:42:54	\N	\N	\N	verified	\N
46	Dean CCIT	dean-ccit@nu-lipa.edu.ph	2026-08-21 04:46:56	$2y$12$BkjnrUpWeuh9Fe4K92pCqu9RB06OwgdT9Jb/JI0tZHaAzn4c2IdFK	82dShr0IqKteqohzp7fXYukwnC2LzVtRgNxoeUU3VBTTNeaR3T60wJDWBWtu	2026-08-21 04:46:56	2026-08-24 04:20:30	\N	\N	\N	verified	\N
42	SDAO Member B	sdao-b@nu-lipa.edu.ph	2026-08-21 04:46:48	$2y$12$BkjnrUpWeuh9Fe4K92pCqu9RB06OwgdT9Jb/JI0tZHaAzn4c2IdFK	VrA4cIRCMRlXRbOn0915on562kDvp76ly5bvZ9L17Lnwa9Hln6mUOx8NFmDV	2026-08-21 04:46:48	2026-08-24 04:20:24	\N	\N	\N	verified	\N
47	Chair CS	chair-cs@nu-lipa.edu.ph	2026-08-21 04:46:58	$2y$12$BkjnrUpWeuh9Fe4K92pCqu9RB06OwgdT9Jb/JI0tZHaAzn4c2IdFK	GYTVplr6losbkFvgv10ahDFbl4ZpUNeCl0hFJnLIWZClqGYSX7vvaUBGIuAi	2026-08-21 04:46:58	2026-08-24 04:20:31	\N	\N	\N	verified	\N
27	Carolyn D. Matira	matiracd@nu-lipa.edu.ph	2026-08-19 14:49:03	$2y$12$GtjHz396FkTPwALvsEIV.OOsd1WY316KCzH2V.ZXLR5w4w37fKu/y	fW5kclQcvOX3u9uWa37nOAieqnePKQk5mRYpEVmK4OqIVrG8YJqHhyiNLoIN	2026-08-19 14:49:03	2026-08-21 04:42:18	\N	\N	\N	verified	\N
24	Bernie S. Fabito	fabitobs@nu-lipa.edu.ph	2026-08-19 14:48:56	$2y$12$i3xHlVUt3joSp.U6HQwPHuc5ZHesEI7FLea3Z3C3H/TxC1bgplAHu	xkb9knCV10VExp5vHx4ds8MYuHbuvNGpYAEPBJqOW4cUbzY88EBrUbp0DxTe	2026-08-19 14:48:56	2026-08-21 04:42:10	\N	\N	\N	verified	\N
23	Pia Jasmin I. Quizon	quizonpi@nu-lipa.edu.ph	2026-08-19 14:48:54	$2y$12$DBn3okpF0txxVrfcXfYfMe0q7SyRg9p5X6crLcIp6DQereEAE2Ati	q8XDUNNavrJXOXPxE8UmG4GaSWhf5DQENDBcKlF6TZJOPXaFmvxpQ7VvSFye	2026-08-19 14:48:54	2026-08-21 04:42:08	\N	\N	\N	verified	\N
41	SDAO Member A	sdao-a@nu-lipa.edu.ph	2026-08-21 04:46:44	$2y$12$BkjnrUpWeuh9Fe4K92pCqu9RB06OwgdT9Jb/JI0tZHaAzn4c2IdFK	xe8z6FK5Fef20LYDgo8ehsBDY9CDpiDAbTXmf1Gczuu9WSa5ktmF7KzAlzGa	2026-08-21 04:46:44	2026-08-24 04:20:22	\N	\N	\N	verified	\N
25	Avelino D. Palupit	palupitad@nu-lipa.edu.ph	2026-08-19 14:48:58	$2y$12$Pr6fC4NXSmLiddZFL9UhU.dZlYfgkD06x2.xqK0LeKWf6wLY6BTcq	jg0oLvUOlfxfxz8lsUAHtBPMaQQdukKJac5GyI2nSaLOh3lZhduVKM0KcLwP	2026-08-19 14:48:58	2026-08-21 04:42:13	\N	\N	\N	verified	\N
29	Sir Joseph Michael E. Aramil	aramilje@nu-lipa.edu.ph	2026-08-19 14:49:08	$2y$12$syYp4eS7bQwuZhRoHszkHOtrr6XBWq7YPqd/lhkrtIu9Hv/mgZswO	4Oo4ACeN1JP6buBxUAncIiSkZtEHOI6o1WNAAVB4GjJRlYyVHLvSF1bmzyeJ	2026-08-19 14:49:08	2026-08-21 04:42:24	\N	\N	\N	verified	\N
50	Chair IT	chair-it@nu-lipa.edu.ph	2026-08-21 04:47:05	$2y$12$BkjnrUpWeuh9Fe4K92pCqu9RB06OwgdT9Jb/JI0tZHaAzn4c2IdFK	WTKkOKDaEStlPqyQD6OdSXZ0dCSXx1XP4yBJmrIeDFYsMyBl8eAZzqKfX0g0	2026-08-21 04:47:05	2026-08-24 04:20:36	\N	\N	\N	verified	\N
96	Andrea Villareal	villarreala@students.nu-lipa.edu.ph	2026-08-21 05:32:04	$2y$12$.g8D2JCD6PeiLSbrr2ICZO2s/NW/bsDzf4BDGjNn/u88SMYrSAysW	C2EVvmXv7BefQ8uVVVMhnWMELpn0VT9szdrquIsymzKRgdUR8mxc3e8sj3q8	2026-08-21 05:32:04	2026-08-21 05:32:04	\N	\N	\N	verified	2024-300006
85	Engr. Michael Roxas	roxasm@nu-lipa.edu.ph	2026-08-21 05:31:43	$2y$12$nOzhFCSU/8R0YERdNU98SO1Xc8HxAswF1Irs5qiGfUgJeASBiEsDa	2Qmt9AGFjzANktAgtsp3gFGtaLKBTSTA6xhGi9OBL4k6RVZQ7XOGnZGc4eA1	2026-08-21 05:31:43	2026-08-21 05:31:43	\N	\N	\N	verified	\N
99	Bianca Fernandez	fernandezb@students.nu-lipa.edu.ph	2026-08-21 05:32:07	$2y$12$y9tvHQF3c1mNTuCO2M2K7O6zaWfpdT24jQK7cPPks.ce9yyRWxKFy	hnvKVftbAMFi7dT7eeO5dm4jhCSd3lacg1wVS6z3oV8gDkFcNaYl1XtmOBtZ	2026-08-21 05:32:07	2026-08-21 05:32:07	\N	\N	\N	verified	2024-300009
86	Dr. Fatima Santiago	santiagof@nu-lipa.edu.ph	2026-08-21 05:31:44	$2y$12$.JbAP9tHyfy/2h6CpMWl/OucekL6mL9xh5ltNn7YQQkcIcC0AOhf6	uY6SVX80P0	2026-08-21 05:31:44	2026-08-21 05:31:44	\N	\N	\N	verified	\N
88	Grace Villanueva	villanuevag@nu-lipa.edu.ph	2026-08-21 05:31:46	$2y$12$5YaphKNgFUwhdB0NxxBBHeP5XfmndFWkINHd6mgpcd1ZTWm5VhFnO	EcukOl3taf	2026-08-21 05:31:46	2026-08-21 05:31:46	\N	\N	\N	verified	\N
89	Nora Espiritu	espiritun@nu-lipa.edu.ph	2026-08-21 05:31:47	$2y$12$OvGzQilx4/.YS9ljEBLtyuachvxHlGPDmZnvt0toDb5v.Iy2fAZDC	4ro3fvKRVj	2026-08-21 05:31:47	2026-08-21 05:31:47	\N	\N	\N	verified	\N
90	Dr. Cristina Bautista	bautistac@nu-lipa.edu.ph	2026-08-21 05:31:53	$2y$12$SE87xplLrfx//2mhdNGKXeiCNYi2V1ilbLo787.yMR.tOEefksRqO	yop05GgE7U	2026-08-21 05:31:53	2026-08-21 05:31:53	\N	\N	\N	verified	\N
92	Isabelle Reyes	reyesi@students.nu-lipa.edu.ph	2026-08-21 05:31:59	$2y$12$rUWbL7V/aeTZQFd52ssLSeoiL2YvDGMG9eSMw.yCdS6wpceyQxbte	Yr0Xwy4Xxy	2026-08-21 05:31:59	2026-08-21 05:31:59	\N	\N	\N	verified	2024-300002
93	Nathaniel Cruz	cruzn@students.nu-lipa.edu.ph	2026-08-21 05:32:01	$2y$12$/xCwAOK9lHaITt7c32PIfu2UMCArysdSwamA9WdwzwR95.ZURoVBK	ezPGsYQ27h	2026-08-21 05:32:01	2026-08-21 05:32:01	\N	\N	\N	verified	2024-300003
94	Samantha Diaz	diazs@students.nu-lipa.edu.ph	2026-08-21 05:32:02	$2y$12$W.yWEXcCmlww6la0zgIQJ.RA9Mxs9jnjjaA4GFJspemRRsJe81Z7S	jQ9I9AtAIe	2026-08-21 05:32:02	2026-08-21 05:32:02	\N	\N	\N	verified	2024-300004
100	Christian Aquino	aquinoc@students.nu-lipa.edu.ph	2026-08-21 05:32:08	$2y$12$THzYKRXBuRJFEgbyFGMebunafYnFj8Oyxm/uvXnVkKgKG.7GWhUoK	ic33tPpvSd	2026-08-21 05:32:08	2026-08-21 05:32:08	\N	\N	\N	verified	2024-300010
102	Ella Marquez	marqueze@students.nu-lipa.edu.ph	2026-08-21 05:32:11	$2y$12$52eAtGrm/IuTceSBmqcky.pc/Ebjf2W4NBZjX0npE2etzMh7vD7Ym	njTRWMI4Wu	2026-08-21 05:32:11	2026-08-21 05:32:11	\N	\N	\N	verified	2024-300012
103	Francis Ocampo	ocampof@students.nu-lipa.edu.ph	2026-08-21 05:32:12	$2y$12$30up11.LQi2ecTccz2jBBO2Z4DBLuONF/Izm/HVD5I3BmJDtvVBwq	wxvShcj6Jx	2026-08-21 05:32:12	2026-08-21 05:32:12	\N	\N	\N	verified	2024-300013
104	Grace Lim	limg@students.nu-lipa.edu.ph	2026-08-21 05:32:13	$2y$12$C0DHSpfmExzMXlVr5uZlc.73w82qvezIYRQnGxpqxgxktrCj3ITYK	IhOqwSOvBf	2026-08-21 05:32:13	2026-08-21 05:32:13	\N	\N	\N	verified	2024-300014
105	Harold Navarro	navarroh@students.nu-lipa.edu.ph	2026-08-21 05:32:14	$2y$12$odVmlfQ.MgdayGimITDgw.bp93Vm2KbXrq30rUnDP43wGkBHtfeU2	2vxL0HBQjY	2026-08-21 05:32:14	2026-08-21 05:32:14	\N	\N	\N	verified	2024-300015
106	Louis Serrano	serranol@students.nu-lipa.edu.ph	2026-08-21 05:32:15	$2y$12$qdQSwix1twoaMJ5vpYT/m.cY7S4eqG2OmOuwpAk449JDveOSz3Zs2	ljWjKCep6l	2026-08-21 05:32:15	2026-08-21 05:32:15	\N	\N	\N	verified	2024-300016
107	Katrina Valdez	valdezk@students.nu-lipa.edu.ph	2026-08-21 05:32:16	$2y$12$tambsVey/g7G.toqj6KRxup0TOaDt.MWySiaUpY9sqYvRex2aa/5W	B2sQWwhBly	2026-08-21 05:32:16	2026-08-21 05:32:16	\N	\N	\N	verified	2024-300017
108	Rafael Espino	espinor@students.nu-lipa.edu.ph	2026-08-21 05:32:17	$2y$12$yfDpuWWvbREW/ULoMyxv0OJQJxWPl65MlgtPX/DW5wOy7EWzgr.Bi	am9byURKd6	2026-08-21 05:32:17	2026-08-21 05:32:17	\N	\N	\N	unverified	2024-300018
109	Trisha Bonifacio	bonifaciot@students.nu-lipa.edu.ph	2026-08-21 05:32:19	$2y$12$Yh0OBlaou90906Tq0K8X2e2Pty.oe9MW8m3VRnRx7N8SZQp.lBe06	IZdzvPb6Ol	2026-08-21 05:32:19	2026-08-21 05:32:19	\N	\N	\N	unverified	2024-300019
110	Julian Castro	castroj@students.nu-lipa.edu.ph	2026-08-21 05:32:20	$2y$12$HxNlF9GGrI6d0EIWi9B63.mzSBWpL6HXBzLKDzI9VLAKNgUPP8whO	UKy73Dkx3r	2026-08-21 05:32:20	2026-08-21 05:32:20	\N	\N	\N	unverified	2024-300020
111	Vince Aguilar	aguilarv@students.nu-lipa.edu.ph	2026-08-21 05:32:21	$2y$12$65Q.VtswIwrjWD8ducNeA.n3HneiFkh4GwTz.Le/Vpng9TPKEeAH.	fP04FQRfsl	2026-08-21 05:32:21	2026-08-21 05:32:21	\N	\N	\N	rejected	2024-300021
101	Denise Manalo	manalod@students.nu-lipa.edu.ph	2026-08-21 05:32:10	$2y$12$.K4TAh0ZFMjn33o0kJfM.OFLqNmj7Q66wvTQGxYMPS3KQgz.7Ujpa	bCI5IzFJpxm5L1Lxx1I7ga1Go3ZoUCagC1uudJEwg6ZvhhK51MLpMe7MinWh	2026-08-21 05:32:10	2026-08-21 05:32:10	\N	\N	\N	verified	2024-300011
91	Miguel Torres	torresm@students.nu-lipa.edu.ph	2026-08-21 05:31:58	$2y$12$p3uok/IdydltkCOegtdEE.nhySo0NwVi62zyDEVPQgkTKsWTE4PZC	kYOc9vr2ZHdWQLyNhOxgI5Ke9ctzvyxRvDeuUAzfQUZfd3mSnkQljLkw9dKt	2026-08-21 05:31:58	2026-08-21 05:31:58	\N	\N	\N	verified	2024-300001
122	Eren Yaeger	ereny@nu-lipa.edu.ph	2026-08-24 06:10:26	$2y$12$7a3r32U1BGUz2e8vZHuqdOZGnlsRNobkbOjs5s0DeKktNoxg3p39W	\N	2026-08-24 06:10:26	2026-08-24 06:10:26	\N	\N	\N	verified	\N
97	Patricia Gomez	gomezp@students.nu-lipa.edu.ph	2026-08-21 05:32:05	$2y$12$2MLz.ZVqExNGhlQAprn42.y3AJP1dgtuRjGuZWVf81dfHXCVAkprm	36cLs3IPnGeYIKcgo2RPb4Ci0H1aE7SYyl94a2Pg6x4ghd39fTcAfI8gqLqR	2026-08-21 05:32:05	2026-08-21 05:32:05	\N	\N	\N	verified	2024-300007
98	Joshua Ramos	ramosj@students.nu-lipa.edu.ph	2026-08-21 05:32:06	$2y$12$GOuVswwDgr6aq.PPLw7mUu7HiF0ZMMyM/9dmX/CG5xWeGJGdRXF4W	u7ALZsxcySmKTasM22D4xgz1oxmd5wERTvX8EwnMPZ9myVUVbmVaTeL64cWu	2026-08-21 05:32:06	2026-08-21 05:32:06	\N	\N	\N	verified	2024-300008
95	Kevin Mendoza	mendozak@students.nu-lipa.edu.ph	2026-08-21 05:32:03	$2y$12$xR3/CP1HDzlrx6/RR3oTjuhSsHDmQo76olvbuDP5B2U1/DoHdjKri	pTuCknjv5o1sOEGCFAtBnd7GgXWBRLbD39y3Ulcd4OHcjHz989xxPce1o1jp	2026-08-21 05:32:03	2026-08-21 05:32:03	\N	\N	\N	verified	2024-300005
128	Juancho Marudo	hernandezgv@students.nu-lipa.edu.ph	2026-08-24 14:22:35	$2y$12$WfO3ddCxK7qSTT982K4CdembrFT5gHjjZVN8TvdyhaDiS.XRdmfy.	\N	2026-08-24 14:22:35	2026-08-24 14:23:23	\N	\N	\N	verified	2023-182138
43	Asst. Director of Academic Services	asst-director@nu-lipa.edu.ph	2026-08-21 04:46:48	$2y$12$BkjnrUpWeuh9Fe4K92pCqu9RB06OwgdT9Jb/JI0tZHaAzn4c2IdFK	XrgWe5yibUFoGMjQkoLm4czwgUObwVnN7cjT8dv5OnZ7T2FL69F6WkVm8cXK	2026-08-21 04:46:48	2026-08-24 04:20:25	\N	\N	\N	verified	\N
87	Ramon Dela Cruz	delacruzr@nu-lipa.edu.ph	2026-08-21 05:31:45	$2y$12$ipwAEs3XoYEfyRf4EcDniuD59b3HAdhCkAVNf78t519d5qfdgPsEy	tvDK1Xqtv4XTpannppYAwZ2anrXo38GQ17NAdpatuz9IqgBTdQl8k6gtaFxX	2026-08-21 05:31:45	2026-08-21 05:31:45	\N	\N	\N	verified	\N
131	Karl T. Zy	karltzy@students.nu-lipa.edu.ph	2026-08-30 02:33:50	$2y$12$YwMZp7ZarlrJeIxzTd66AOVUEp5ncgKxvDiZhWphE9.aNYJWvPSdu	\N	2026-08-30 02:33:50	2026-08-30 02:34:27	\N	\N	\N	verified	2023-182835
132	Ma Mitch	mamitch@nu-lipa.edu.ph	2026-08-30 02:41:30	$2y$12$0HEHjWYDUpSt/YX1Ye.Z7O05G7p4H5qz5MT/dJIMLpXBotpmvT472	\N	2026-08-30 02:41:30	2026-08-30 02:41:30	\N	\N	\N	verified	\N
133	Testing 1	testing1@students.nu-lipa.edu.ph	2026-08-30 04:12:12	$2y$12$Tz109lrue7ndXLYJRkct6e4wLJchpWPX5hMHyJQEj8CjDY5Dq8GXO	\N	2026-08-30 04:12:12	2026-08-30 04:12:19	\N	\N	\N	verified	2023-182381
134	Adviser Testing	adviser-testing@nu-lipa.edu.ph	2026-08-30 04:12:54	$2y$12$9gh8fteNXuODK22HVihgo.88sxrrh/CfUYCvqE69pOKJKjoyXRLpy	\N	2026-08-30 04:12:54	2026-08-30 04:12:54	\N	\N	\N	verified	\N
135	Testing 2	testing2@students.nu-lipa.edu.ph	2026-08-30 04:14:56	$2y$12$qMbxLhzlXuRKZvjHtuOMqeE.f7l.vSd7Yygf0F7dHKAErKuKrkk/K	\N	2026-08-30 04:14:56	2026-08-30 04:15:05	\N	\N	\N	verified	2023-718237
136	New Testing Adv	newtestingadv@nu-lipa.edu.ph	2026-08-30 05:07:18	$2y$12$vFM9fmdp6jhrwRgQMQOc3eo4Ug7xBytqjImjI/6BHHBZ9Q8Z1QpO6	\N	2026-08-30 05:07:18	2026-08-30 05:07:18	\N	\N	\N	verified	\N
137	Gennice Pogi	gennice@students.nu-lipa.edu.ph	2026-08-30 08:33:11	$2y$12$h.wK/M/kfdWNugiVFyfkyOORF.zooi4TCQP8MlsHSZeoEoBtCgwsu	\N	2026-08-30 08:33:11	2026-08-30 08:33:38	\N	\N	\N	verified	2023-191199
138	Rano El	ranoel@students.nu-lipa.edu.ph	2026-08-30 14:39:50	$2y$12$ZJRFxxAnoOZHRdjnIIYXcOLKhU/r8Yccl9ScYXdGxO5dVid4Hqg3q	\N	2026-08-30 14:39:50	2026-08-30 14:39:58	\N	\N	\N	verified	2021-312831
121	Ranuel Glenn Viray	virayrl@students.nu-lipa.edu.ph	2026-08-24 05:13:12	$2y$12$Qah1LRIqgliFtMZs8KWfnOB5ltS5ntZUlIAnAwSpdE7Y5qmaypg0C	nyDgRG0Oft5gNAACj3fYzMGAv3GLxyuwjkd3mejzas755DLkYIaPi9WAOXBa	2026-08-24 05:13:12	2026-08-24 05:20:11	eyJpdiI6IjBmS2NOM1NleStDQ292ekdTQnhEZWc9PSIsInZhbHVlIjoiVjYyYzZaSFdDODNIbm1QWENwclBpc1lFTEtiK1ZrMVNQcDF2bGF4R3ZyST0iLCJtYWMiOiI2NWE3ZmZhNjNiNGI2ZjNlNDI4Mjc4MWI5YzFkNTFjNTZhMjUzNTQ5ZGIxYjRiZTBiMzZiOTMzZTE0ZTlhZDEwIiwidGFnIjoiIn0=	eyJpdiI6ImxJdkFmdmR4dG0rM09obXNzVjJXYUE9PSIsInZhbHVlIjoiN3UyQzlKaWtyUEF3Ty9BZWVzK3JyTXdUcGpSemZiSWJNdnBYR1d3SU1nSWlIOUZ1d1hleDNuOG9DeHNseWRud1I0L1FmaS9sZE1hZy93M1pvRTcxRnpFTHcyRWx6SUZHbmZ1dDdhZGpMb09ZOGdMbUwwMXcweWFVZEo4OTBFa2ZEWXFvelBwWG9NRVR6S0M4Q0JyUkxJWUJiZTg5QkpsdkRKd0NrSjlQbWtWOEVXUEI1bEpDZTZBdlNWUElGdWV2QjhXZzVpV1V4bW9nckovWVo1ZlltZG0zMHNTOUJ2LzdCeUwwOXBpa3BhdThjZzRsTFZzbU9qMUpFTXI1QWYzWkpmZ0Rya1Fhdm1LeVVKZVNKOVEvVHc9PSIsIm1hYyI6IjYwN2Q2MGU3ZmVjMTM2YzdiNzk5NTk2ZjBhZjMwN2RkNjM3MjNmY2YwZTFjZDk3NzQ1MmRiNmVlNGFjMzRlNTQiLCJ0YWciOiIifQ==	\N	verified	2023-181229
139	Alcantara, Kristian Diether A.	alcantaraka@students.nu-lipa.edu.ph	2026-09-03 08:01:26	$2y$12$krQXyNbZ9Nt5sRtfttnazuRZLLkLwVoKlH55HdVpk7jJJpa451IKa	\N	2026-09-03 08:01:26	2026-09-03 08:01:46	\N	\N	\N	verified	2023-182834
140	Marvin Atanacio	marvin.atanacio@nu-lipa.edu.ph	2026-09-03 08:05:20	$2y$12$i/.ra2KAyMBRWm9cbcfMg.8msVxeGtdwwz/3jUskiJ8Av6yGCJxPK	\N	2026-09-03 08:05:20	2026-09-03 08:05:20	\N	\N	\N	verified	\N
21	Carl Justin Magpantay	magpantayc@nu-lipa.edu.ph	2026-08-19 14:48:50	$2y$12$s2fO6vJj.dK.Pmoy1JqqnuBUYHqjeYM2NHZyiu7B5Y/JCw.U1IZo2	BYqxZUKP9kh4YOrdYMUPlDOUvM11lEmRKgcSne6UhHXaKPNxKKZH3RNOTa1R	2026-08-19 14:48:51	2026-08-21 04:42:04	\N	\N	\N	verified	\N
142	SABM Adviser	sabmadviser@nu-lipa.edu.ph	2026-09-05 13:36:45	$2y$12$WLikfSnoYlHzbJRaolpHzeVfpwwFgr5xBzeQNcw8.kLumzgRXegzi	\N	2026-09-05 13:36:45	2026-09-05 13:36:45	\N	\N	\N	verified	\N
141	Gennice Marcaida	gennicemarcaida@students.nu-lipa.edu.ph	2026-09-05 13:36:25	$2y$12$u2ji69C/36kXnk3NsCcMwe3Y/6Hk.PQ9Bp5h5BUixK7vzi8miLvFu	\N	2026-09-05 13:36:25	2026-09-05 13:36:52	\N	\N	\N	verified	2023-182833
\.


--
-- Data for Name: workflow_steps; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.workflow_steps (id, workflow_template_id, "position", role, required_approvals, created_at, updated_at) FROM stdin;
1	1	1	sdao_member	2	2026-07-13 07:47:41	2026-07-13 07:47:41
2	2	1	sdao_member	2	2026-07-13 07:47:42	2026-07-13 07:47:42
3	3	1	sdao_member	2	2026-07-13 07:47:43	2026-07-13 07:47:43
4	4	1	sdao_member	2	2026-07-13 07:47:44	2026-07-13 07:47:44
5	5	1	adviser	1	2026-07-13 07:47:45	2026-07-13 07:47:45
6	5	2	program_chair	1	2026-07-13 07:47:45	2026-07-13 07:47:45
7	5	3	dean	1	2026-07-13 07:47:46	2026-07-13 07:47:46
8	5	4	sdao_member	2	2026-07-13 07:47:46	2026-07-13 07:47:46
9	5	5	assistant_director_academic_services	1	2026-07-13 07:47:46	2026-07-13 07:47:46
10	5	6	academic_director	1	2026-07-13 07:47:47	2026-07-13 07:47:47
11	5	7	executive_director	1	2026-07-13 07:47:47	2026-07-13 07:47:47
12	6	1	sdao_member	2	2026-07-13 07:47:48	2026-07-13 07:47:48
13	6	2	adviser	1	2026-07-13 07:47:49	2026-07-13 07:47:49
14	6	3	program_chair	1	2026-07-13 07:47:49	2026-07-13 07:47:49
15	6	4	dean	1	2026-07-13 07:47:49	2026-07-13 07:47:49
16	6	5	assistant_director_academic_services	1	2026-07-13 07:47:50	2026-07-13 07:47:50
17	6	6	academic_director	1	2026-07-13 07:47:50	2026-07-13 07:47:50
18	6	7	executive_director	1	2026-07-13 07:47:51	2026-07-13 07:47:51
19	7	1	adviser	1	2026-07-13 07:47:52	2026-07-13 07:47:52
20	7	2	principal	1	2026-07-13 07:47:52	2026-07-13 07:47:52
21	7	3	sdao_member	2	2026-07-13 07:47:52	2026-07-13 07:47:52
22	7	4	assistant_director_academic_services	1	2026-07-13 07:47:53	2026-07-13 07:47:53
23	7	5	academic_director	1	2026-07-13 07:47:53	2026-07-13 07:47:53
24	7	6	executive_director	1	2026-07-13 07:47:54	2026-07-13 07:47:54
25	8	1	sdao_member	2	2026-07-13 07:47:55	2026-07-13 07:47:55
26	8	2	adviser	1	2026-07-13 07:47:55	2026-07-13 07:47:55
27	8	3	principal	1	2026-07-13 07:47:56	2026-07-13 07:47:56
28	8	4	assistant_director_academic_services	1	2026-07-13 07:47:56	2026-07-13 07:47:56
29	8	5	academic_director	1	2026-07-13 07:47:56	2026-07-13 07:47:56
30	8	6	executive_director	1	2026-07-13 07:47:57	2026-07-13 07:47:57
\.


--
-- Data for Name: workflow_templates; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.workflow_templates (id, form_type, variant, name, created_at, updated_at) FROM stdin;
1	organization_registration	\N	Organization Registration	2026-07-13 07:47:41	2026-07-13 07:47:41
2	organization_renewal	\N	Organization Renewal	2026-07-13 07:47:42	2026-07-13 07:47:42
3	activity_calendar	\N	Activity Calendar	2026-07-13 07:47:43	2026-07-13 07:47:43
4	after_activity_report	\N	After-Activity Report	2026-07-13 07:47:43	2026-07-13 07:47:43
5	activity_proposal	regular_on_calendar	Activity Proposal — Regular School, On-Calendar	2026-07-13 07:47:44	2026-07-13 07:47:44
6	activity_proposal	regular_off_calendar	Activity Proposal — Regular School, Off-Calendar	2026-07-13 07:47:48	2026-07-13 07:47:48
7	activity_proposal	shs_on_calendar	Activity Proposal — Senior High School, On-Calendar	2026-07-13 07:47:51	2026-07-13 07:47:51
8	activity_proposal	shs_off_calendar	Activity Proposal — Senior High School, Off-Calendar	2026-07-13 07:47:54	2026-07-13 07:47:54
\.


--
-- Data for Name: schema_migrations; Type: TABLE DATA; Schema: realtime; Owner: supabase_admin
--

COPY realtime.schema_migrations (version, inserted_at) FROM stdin;
20211116024918	2026-08-29 06:40:33
20211116045059	2026-08-29 06:40:33
20211116050929	2026-08-29 06:40:33
20211116051442	2026-08-29 06:40:33
20211116212300	2026-08-29 06:40:33
20211116213355	2026-08-29 06:40:33
20211116213934	2026-08-29 06:40:33
20211116214523	2026-08-29 06:40:33
20211122062447	2026-08-29 06:40:33
20211124070109	2026-08-29 06:40:33
20211202204204	2026-08-29 06:40:33
20211202204605	2026-08-29 06:40:33
20211210212804	2026-08-29 06:40:33
20211228014915	2026-08-29 06:40:33
20220107221237	2026-08-29 06:40:33
20220228202821	2026-08-29 06:40:33
20220312004840	2026-08-29 06:40:33
20220603231003	2026-08-29 06:40:33
20220603232444	2026-08-29 06:40:33
20220615214548	2026-08-29 06:40:33
20220712093339	2026-08-29 06:40:33
20220908172859	2026-08-29 06:40:33
20220916233421	2026-08-29 06:40:33
20230119133233	2026-08-29 06:40:33
20230128025114	2026-08-29 06:40:33
20230128025212	2026-08-29 06:40:33
20230227211149	2026-08-29 06:40:33
20230228184745	2026-08-29 06:40:33
20230308225145	2026-08-29 06:40:33
20230328144023	2026-08-29 06:40:33
20231018144023	2026-08-29 06:40:33
20231204144023	2026-08-29 06:40:33
20231204144024	2026-08-29 06:40:33
20231204144025	2026-08-29 06:40:33
20240108234812	2026-08-29 06:40:33
20240109165339	2026-08-29 06:40:33
20240227174441	2026-08-29 06:40:33
20240311171622	2026-08-29 06:40:33
20240321100241	2026-08-29 06:40:33
20240401105812	2026-08-29 06:40:33
20240418121054	2026-08-29 06:40:33
20240523004032	2026-08-29 06:40:33
20240618124746	2026-08-29 06:40:33
20240801235015	2026-08-29 06:40:33
20240805133720	2026-08-29 06:40:33
20240827160934	2026-08-29 06:40:33
20240919163303	2026-08-29 06:40:33
20240919163305	2026-08-29 06:40:33
20241019105805	2026-08-29 06:40:33
20241030150047	2026-08-29 06:40:33
20241108114728	2026-08-29 06:40:33
20241121104152	2026-08-29 06:40:33
20241130184212	2026-08-29 06:40:33
20241220035512	2026-08-29 06:40:33
20241220123912	2026-08-29 06:40:33
20241224161212	2026-08-29 06:40:33
20250107150512	2026-08-29 06:40:33
20250110162412	2026-08-29 06:40:33
20250123174212	2026-08-29 06:40:33
20250128220012	2026-08-29 06:40:33
20250506224012	2026-08-29 06:40:33
20250523164012	2026-08-29 06:40:33
20250714121412	2026-08-29 06:40:33
20250905041441	2026-08-29 06:40:33
20251103001201	2026-08-29 06:40:33
20251120212548	2026-08-29 06:40:33
20251120215549	2026-08-29 06:40:33
20260218120000	2026-08-29 06:40:33
20260326120000	2026-08-29 06:40:33
20260514120000	2026-08-29 06:40:33
20260527120000	2026-08-29 06:40:33
20260528120000	2026-08-29 06:40:33
20260603120000	2026-08-29 06:40:33
20260605120000	2026-08-29 06:40:33
20260606110000	2026-08-29 06:40:33
20260616120000	2026-08-29 06:40:33
20260624120000	2026-08-29 06:40:33
20260626120000	2026-08-29 06:40:33
20260706120000	2026-08-29 06:40:33
20260707120000	2026-08-29 06:40:33
20260709120000	2026-08-29 06:40:33
20260714120000	2026-09-05 12:45:29
\.


--
-- Data for Name: subscription; Type: TABLE DATA; Schema: realtime; Owner: supabase_realtime_admin
--

COPY realtime.subscription (id, subscription_id, entity, filters, claims, created_at, action_filter, selected_columns) FROM stdin;
\.


--
-- Data for Name: buckets; Type: TABLE DATA; Schema: storage; Owner: supabase_storage_admin
--

COPY storage.buckets (id, name, owner, created_at, updated_at, public, avif_autodetection, file_size_limit, allowed_mime_types, owner_id, type, versioning_status) FROM stdin;
documents	documents	\N	2026-08-17 13:03:26.611939+00	2026-08-17 13:03:26.611939+00	f	f	\N	\N	\N	STANDARD	DISABLED
\.


--
-- Data for Name: buckets_analytics; Type: TABLE DATA; Schema: storage; Owner: supabase_storage_admin
--

COPY storage.buckets_analytics (name, type, format, created_at, updated_at, id, deleted_at) FROM stdin;
\.


--
-- Data for Name: buckets_vectors; Type: TABLE DATA; Schema: storage; Owner: supabase_storage_admin
--

COPY storage.buckets_vectors (id, type, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: storage; Owner: supabase_storage_admin
--

COPY storage.migrations (id, name, hash, executed_at) FROM stdin;
0	create-migrations-table	e18db593bcde2aca2a408c4d1100f6abba2195df	2026-08-29 05:10:21.130973
1	initialmigration	6ab16121fbaa08bbd11b712d05f358f9b555d777	2026-08-29 05:10:21.171758
2	storage-schema	f6a1fa2c93cbcd16d4e487b362e45fca157a8dbd	2026-08-29 05:10:21.174806
3	pathtoken-column	2cb1b0004b817b29d5b0a971af16bafeede4b70d	2026-08-29 05:10:21.196017
4	add-migrations-rls	427c5b63fe1c5937495d9c635c263ee7a5905058	2026-08-29 05:10:21.209102
5	add-size-functions	79e081a1455b63666c1294a440f8ad4b1e6a7f84	2026-08-29 05:10:21.212426
6	change-column-name-in-get-size	ded78e2f1b5d7e616117897e6443a925965b30d2	2026-08-29 05:10:21.215879
7	add-rls-to-buckets	e7e7f86adbc51049f341dfe8d30256c1abca17aa	2026-08-29 05:10:21.21972
8	add-public-to-buckets	fd670db39ed65f9d08b01db09d6202503ca2bab3	2026-08-29 05:10:21.22274
9	fix-search-function	af597a1b590c70519b464a4ab3be54490712796b	2026-08-29 05:10:21.226025
10	search-files-search-function	b595f05e92f7e91211af1bbfe9c6a13bb3391e16	2026-08-29 05:10:21.22926
11	add-trigger-to-auto-update-updated_at-column	7425bdb14366d1739fa8a18c83100636d74dcaa2	2026-08-29 05:10:21.233434
12	add-automatic-avif-detection-flag	8e92e1266eb29518b6a4c5313ab8f29dd0d08df9	2026-08-29 05:10:21.237042
13	add-bucket-custom-limits	cce962054138135cd9a8c4bcd531598684b25e7d	2026-08-29 05:10:21.240525
14	use-bytes-for-max-size	941c41b346f9802b411f06f30e972ad4744dad27	2026-08-29 05:10:21.243854
15	add-can-insert-object-function	934146bc38ead475f4ef4b555c524ee5d66799e5	2026-08-29 05:10:21.26746
16	add-version	76debf38d3fd07dcfc747ca49096457d95b1221b	2026-08-29 05:10:21.270984
17	drop-owner-foreign-key	f1cbb288f1b7a4c1eb8c38504b80ae2a0153d101	2026-08-29 05:10:21.274932
18	add_owner_id_column_deprecate_owner	e7a511b379110b08e2f214be852c35414749fe66	2026-08-29 05:10:21.277853
19	alter-default-value-objects-id	02e5e22a78626187e00d173dc45f58fa66a4f043	2026-08-29 05:10:21.283076
20	list-objects-with-delimiter	cd694ae708e51ba82bf012bba00caf4f3b6393b7	2026-08-29 05:10:21.287583
21	s3-multipart-uploads	8c804d4a566c40cd1e4cc5b3725a664a9303657f	2026-08-29 05:10:21.292853
22	s3-multipart-uploads-big-ints	9737dc258d2397953c9953d9b86920b8be0cdb73	2026-08-29 05:10:21.305268
23	optimize-search-function	9d7e604cddc4b56a5422dc68c9313f4a1b6f132c	2026-08-29 05:10:21.313835
24	operation-function	8312e37c2bf9e76bbe841aa5fda889206d2bf8aa	2026-08-29 05:10:21.317264
25	custom-metadata	d974c6057c3db1c1f847afa0e291e6165693b990	2026-08-29 05:10:21.320772
26	objects-prefixes	215cabcb7f78121892a5a2037a09fedf9a1ae322	2026-08-29 05:10:21.324348
27	search-v2	859ba38092ac96eb3964d83bf53ccc0b141663a6	2026-08-29 05:10:21.327725
28	object-bucket-name-sorting	c73a2b5b5d4041e39705814fd3a1b95502d38ce4	2026-08-29 05:10:21.330636
29	create-prefixes	ad2c1207f76703d11a9f9007f821620017a66c21	2026-08-29 05:10:21.333259
30	update-object-levels	2be814ff05c8252fdfdc7cfb4b7f5c7e17f0bed6	2026-08-29 05:10:21.335971
31	objects-level-index	b40367c14c3440ec75f19bbce2d71e914ddd3da0	2026-08-29 05:10:21.339366
32	backward-compatible-index-on-objects	e0c37182b0f7aee3efd823298fb3c76f1042c0f7	2026-08-29 05:10:21.342153
33	backward-compatible-index-on-prefixes	b480e99ed951e0900f033ec4eb34b5bdcb4e3d49	2026-08-29 05:10:21.344795
34	optimize-search-function-v1	ca80a3dc7bfef894df17108785ce29a7fc8ee456	2026-08-29 05:10:21.348421
35	add-insert-trigger-prefixes	458fe0ffd07ec53f5e3ce9df51bfdf4861929ccc	2026-08-29 05:10:21.351186
36	optimise-existing-functions	6ae5fca6af5c55abe95369cd4f93985d1814ca8f	2026-08-29 05:10:21.353868
37	add-bucket-name-length-trigger	3944135b4e3e8b22d6d4cbb568fe3b0b51df15c1	2026-08-29 05:10:21.356555
38	iceberg-catalog-flag-on-buckets	02716b81ceec9705aed84aa1501657095b32e5c5	2026-08-29 05:10:21.36021
39	add-search-v2-sort-support	6706c5f2928846abee18461279799ad12b279b78	2026-08-29 05:10:21.370155
40	fix-prefix-race-conditions-optimized	7ad69982ae2d372b21f48fc4829ae9752c518f6b	2026-08-29 05:10:21.372977
41	add-object-level-update-trigger	07fcf1a22165849b7a029deed059ffcde08d1ae0	2026-08-29 05:10:21.37582
42	rollback-prefix-triggers	771479077764adc09e2ea2043eb627503c034cd4	2026-08-29 05:10:21.378636
43	fix-object-level	84b35d6caca9d937478ad8a797491f38b8c2979f	2026-08-29 05:10:21.381476
44	vector-bucket-type	99c20c0ffd52bb1ff1f32fb992f3b351e3ef8fb3	2026-08-29 05:10:21.384314
45	vector-buckets	049e27196d77a7cb76497a85afae669d8b230953	2026-08-29 05:10:21.387845
46	buckets-objects-grants	fedeb96d60fefd8e02ab3ded9fbde05632f84aed	2026-08-29 05:10:21.397532
47	iceberg-table-metadata	649df56855c24d8b36dd4cc1aeb8251aa9ad42c2	2026-08-29 05:10:21.401099
48	iceberg-catalog-ids	e0e8b460c609b9999ccd0df9ad14294613eed939	2026-08-29 05:10:21.404268
49	buckets-objects-grants-postgres	072b1195d0d5a2f888af6b2302a1938dd94b8b3d	2026-08-29 05:10:21.418875
50	search-v2-optimised	6323ac4f850aa14e7387eb32102869578b5bd478	2026-08-29 05:10:21.4224
51	index-backward-compatible-search	2ee395d433f76e38bcd3856debaf6e0e5b674011	2026-08-29 05:10:21.906098
52	drop-not-used-indexes-and-functions	5cc44c8696749ac11dd0dc37f2a3802075f3a171	2026-08-29 05:10:21.907508
53	drop-index-lower-name	d0cb18777d9e2a98ebe0bc5cc7a42e57ebe41854	2026-08-29 05:10:21.915899
54	drop-index-object-level	6289e048b1472da17c31a7eba1ded625a6457e67	2026-08-29 05:10:21.917892
55	prevent-direct-deletes	262a4798d5e0f2e7c8970232e03ce8be695d5819	2026-08-29 05:10:21.919001
56	fix-optimized-search-function	b823ed1e418101032fa01374edc9a436e54e3ed4	2026-08-29 05:10:21.923005
57	s3-multipart-uploads-metadata	f127886e00d1b374fadbc7c6b31e09336aad5287	2026-08-29 05:10:21.927312
58	operation-ergonomics	00ca5d483b3fe0d522133d9002ccc5df98365120	2026-08-29 05:10:21.930663
59	drop-unused-functions	38456f13e39691c2bbb4b5151d0d1cdbabd4a8c4	2026-08-29 05:10:21.934367
60	optimize-existing-functions-again	db35e1c91a9201e59f4fef8d972c2f277d68b157	2026-08-29 05:10:21.937713
61	mark-filename-immutable	fe0096517ae9d60aaec1d110172ba9036dc66bb7	2026-08-29 05:10:21.942324
62	object-versioning-core	0b855f00ff3be0bfca91efee02a9858912491a9a	2026-08-29 05:10:21.945723
63	fix-search-name-relative-to-prefix	c7485e417624f795ce8bb2da21927f48e088904d	2026-08-29 05:10:21.952126
64	fix-search-by-timestamp-sqli	0af424ecd388a39bb1645184b222185a12149675	2026-08-29 05:10:21.956824
\.


--
-- Data for Name: objects; Type: TABLE DATA; Schema: storage; Owner: supabase_storage_admin
--

COPY storage.objects (id, bucket_id, name, owner, created_at, updated_at, last_accessed_at, metadata, version, owner_id, user_metadata, archived_at, is_delete_marker, is_versioned) FROM stdin;
77cd8203-e04b-489f-92d5-d0cb57abca09	documents	attachments/organization_registration/1/rbfJmWenyN6bzdxAVqLmGkAdcsBB66j4qSStTITs.pdf	\N	2026-08-18 08:53:31.340815+00	2026-08-18 08:53:31.340815+00	2026-08-18 08:53:31.340815+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:53:31.339Z", "contentLength": 0, "httpStatusCode": 200}	e473a60a-6177-44d5-8147-a2676814f93c	\N	{}	\N	f	f
f76080cf-6bb6-4218-88b3-c04d3b62fbb8	documents	attachments/organization_renewal/17/NfgWt89FgDdEJAJGjiV736JuLj4HGn9AE8w4EPNS.pdf	\N	2026-08-18 08:55:12.628304+00	2026-08-18 08:55:12.628304+00	2026-08-18 08:55:12.628304+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:12.626Z", "contentLength": 0, "httpStatusCode": 200}	9473da80-ce3a-47c2-8753-48b7ef46c77c	\N	{}	\N	f	f
69b0970a-000e-4372-8885-24b043de7634	documents	attachments/organization_registration/1/LoMWPAPqoUzOLkV746e2zwX0Zyf2Pqu8qkiy8MW7.pdf	\N	2026-08-18 08:53:32.435709+00	2026-08-18 08:53:32.435709+00	2026-08-18 08:53:32.435709+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:53:32.433Z", "contentLength": 0, "httpStatusCode": 200}	beb878fb-7657-4de7-b429-75634409e308	\N	{}	\N	f	f
d439d7fb-d24d-435f-90ee-339504a99914	documents	attachments/organization_renewal/48/J8c7RRTFCDfRQuQHpaQAE8Ll2ROVYSK7jaNACQLe.pdf	\N	2026-08-21 06:22:23.183359+00	2026-08-21 06:22:23.183359+00	2026-08-21 06:22:23.183359+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:23.178Z", "contentLength": 0, "httpStatusCode": 200}	50a1a992-b4a3-46a3-a258-6146cfd64b39	\N	{}	\N	f	f
3f4e4ef2-4e09-45b6-a586-96fd6aec2865	documents	attachments/organization_registration/1/ZzW2ngqfhpIZeXd2rdb4IpKNz0KDwF7GKISuCRgu.pdf	\N	2026-08-18 08:53:33.438084+00	2026-08-18 08:53:33.438084+00	2026-08-18 08:53:33.438084+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:53:33.436Z", "contentLength": 0, "httpStatusCode": 200}	951baed5-99cb-4c6f-a578-745263b3ee21	\N	{}	\N	f	f
94fb58ed-fe11-4092-aa59-370af89d95ed	documents	attachments/organization_renewal/17/l1fr4MbTi65CkTvfWb2hxnwnsBh6v6lLp5I4TUdZ.pdf	\N	2026-08-18 08:55:13.485852+00	2026-08-18 08:55:13.485852+00	2026-08-18 08:55:13.485852+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:13.483Z", "contentLength": 0, "httpStatusCode": 200}	fc8413fd-2111-4629-ae30-1c4a3ec3b834	\N	{}	\N	f	f
4fe6b768-3669-4eae-bdef-b48a8d910086	documents	attachments/organization_registration/1/x9er5gmF80s7NVMj0T4mLuGIp0C1S2fIvn2smQbH.pdf	\N	2026-08-18 08:53:34.866355+00	2026-08-18 08:53:34.866355+00	2026-08-18 08:53:34.866355+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:53:34.864Z", "contentLength": 0, "httpStatusCode": 200}	e4a78170-f1a6-42a3-b0a2-3bc4403b48cd	\N	{}	\N	f	f
bf988e45-c952-48ac-a1be-d26236c17727	documents	attachments/organization_registration/1/NyOlymOofI5u0Hce2wnUYzsdlIaUXyMMDGudMYZb.pdf	\N	2026-08-18 08:53:36.306233+00	2026-08-18 08:53:36.306233+00	2026-08-18 08:53:36.306233+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:53:36.304Z", "contentLength": 0, "httpStatusCode": 200}	d78ac2d5-98d9-4f68-a617-3d76c9048e69	\N	{}	\N	f	f
cc73ed5b-bd7a-4d75-96d3-e9532c12520a	documents	attachments/organization_renewal/17/64T4A0RLxbMETSiq4hPqDuNTCdk4oUcn5TreL8q9.pdf	\N	2026-08-18 08:55:14.47981+00	2026-08-18 08:55:14.47981+00	2026-08-18 08:55:14.47981+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:14.478Z", "contentLength": 0, "httpStatusCode": 200}	ad6f4832-d027-4b61-b2ef-0ea3433089a4	\N	{}	\N	f	f
a7f9e3f8-5fd9-4641-aaa1-671c2c5337c1	documents	attachments/organization_registration/1/IfXzWvpCdh7HnwRRczsJsdgJIhhNkFfAjN8ypoSz.pdf	\N	2026-08-18 08:53:52.25134+00	2026-08-18 08:53:52.25134+00	2026-08-18 08:53:52.25134+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:53:52.249Z", "contentLength": 0, "httpStatusCode": 200}	956bb8a9-7cb9-4afb-8a06-d136186cbaed	\N	{}	\N	f	f
0ca8e431-8e82-48ad-bae6-b4a5aa33beeb	documents	attachments/organization_registration/2/QbEvCewOLfeUcNRsPzSVMktuCx6WTukdjqQEmLRQ.pdf	\N	2026-08-18 08:53:53.416982+00	2026-08-18 08:53:53.416982+00	2026-08-18 08:53:53.416982+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:53:53.415Z", "contentLength": 0, "httpStatusCode": 200}	b562ad53-ecaf-4d88-a75f-14924dfbb279	\N	{}	\N	f	f
74759bdd-41d1-44c6-beca-9d858c2a771d	documents	attachments/organization_registration/2/DZN0Z3P6s2Ad01enb6J8Deg1FpZlYMwiIBljldl2.pdf	\N	2026-08-18 08:53:54.328034+00	2026-08-18 08:53:54.328034+00	2026-08-18 08:53:54.328034+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:53:54.326Z", "contentLength": 0, "httpStatusCode": 200}	cadd3f4d-e611-435d-99f1-41205bad5ca5	\N	{}	\N	f	f
e61d16c3-ce6b-403c-ad68-4de68bd0ab8c	documents	attachments/organization_registration/2/WZjFEHbTyN1uKh7zmJNYwz0PPfIjEMCqmLFSDGPg.pdf	\N	2026-08-18 08:53:55.182876+00	2026-08-18 08:53:55.182876+00	2026-08-18 08:53:55.182876+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:53:55.180Z", "contentLength": 0, "httpStatusCode": 200}	20f29868-b787-4ead-a422-5e775f6d2a4a	\N	{}	\N	f	f
fa7aa574-57b2-4d6f-a77c-77c42cb6fab8	documents	attachments/organization_renewal/17/C8UK6bMMeJiCNd6St1d13QxmUye6kEpGWD0ALuHP.pdf	\N	2026-08-18 08:55:15.36605+00	2026-08-18 08:55:15.36605+00	2026-08-18 08:55:15.36605+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:15.363Z", "contentLength": 0, "httpStatusCode": 200}	0639946d-5ad5-4b6f-9cc8-664a7358f4e7	\N	{}	\N	f	f
1f4566c3-f81e-49e8-aad1-e6311a20a3e8	documents	attachments/organization_registration/2/wEXKQGYYT859T4GIYzr5LbxMOKKTAfIt8XDYzfDW.pdf	\N	2026-08-18 08:53:56.135777+00	2026-08-18 08:53:56.135777+00	2026-08-18 08:53:56.135777+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:53:56.133Z", "contentLength": 0, "httpStatusCode": 200}	cb575e55-ffe1-469b-8726-996c8d51c11d	\N	{}	\N	f	f
ed24c863-67af-4f12-9176-961330e87786	documents	attachments/organization_renewal/48/ZsKB4aBghAu9hTObZ7Tv5wsJkciCsvePPiLPdqzW.pdf	\N	2026-08-21 06:22:25.432843+00	2026-08-21 06:22:25.432843+00	2026-08-21 06:22:25.432843+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:25.427Z", "contentLength": 0, "httpStatusCode": 200}	70ba1cd4-6529-45a9-9220-5bafd82f7e9f	\N	{}	\N	f	f
40175adc-0fd4-408e-acae-eb51d2553127	documents	attachments/organization_registration/2/98RiprPLn2vYeLU24wfa9CztdRdnuKC9xp4mf1R4.pdf	\N	2026-08-18 08:53:56.983154+00	2026-08-18 08:53:56.983154+00	2026-08-18 08:53:56.983154+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:53:56.981Z", "contentLength": 0, "httpStatusCode": 200}	7d552523-0bf4-44fb-acef-77afc68d0692	\N	{}	\N	f	f
8a7f352e-e07e-4832-bca3-5fc9c70c4831	documents	attachments/organization_renewal/17/278IExvMbtI721rN4SBq2KDb8ZHAVMewemEpXHjy.pdf	\N	2026-08-18 08:55:16.275462+00	2026-08-18 08:55:16.275462+00	2026-08-18 08:55:16.275462+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:16.273Z", "contentLength": 0, "httpStatusCode": 200}	68a4673c-d742-4258-a1ce-46039ee75011	\N	{}	\N	f	f
51ac8ac7-8ae7-4f11-ac8f-5a8dce123610	documents	attachments/organization_registration/2/z2g7anLpDVioBC7Hmzax224KlKiJdBXBefk6DNMT.pdf	\N	2026-08-18 08:53:57.845963+00	2026-08-18 08:53:57.845963+00	2026-08-18 08:53:57.845963+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:53:57.843Z", "contentLength": 0, "httpStatusCode": 200}	101c615e-d874-4394-a793-2b8b46106077	\N	{}	\N	f	f
b6d1e7c8-0051-43f1-9d9a-01c406984ff0	documents	attachments/organization_registration/3/YdnfYFLUINL2ryuAynpS3Rcvsc7FZLPutrAFO7c7.pdf	\N	2026-08-18 08:53:58.696041+00	2026-08-18 08:53:58.696041+00	2026-08-18 08:53:58.696041+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:53:58.693Z", "contentLength": 0, "httpStatusCode": 200}	8516533c-2671-4567-b1bc-51999f89be15	\N	{}	\N	f	f
920877a7-caa7-4240-b9fb-d4fc08c629c4	documents	attachments/organization_renewal/17/fZjsJbnx7f05xmeoyEaHHD4rGnX4HGt8oTbmZegd.pdf	\N	2026-08-18 08:55:17.224266+00	2026-08-18 08:55:17.224266+00	2026-08-18 08:55:17.224266+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:17.222Z", "contentLength": 0, "httpStatusCode": 200}	8d1eae85-91b2-43da-a48c-f48e097e642b	\N	{}	\N	f	f
737cf6ca-d840-461f-930f-15e43e579e58	documents	attachments/organization_registration/3/rB8QuWo3L1d18zjIVJz34vZyC4SVIRQC6cGHxGCS.pdf	\N	2026-08-18 08:53:59.569755+00	2026-08-18 08:53:59.569755+00	2026-08-18 08:53:59.569755+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:53:59.568Z", "contentLength": 0, "httpStatusCode": 200}	38f41ee7-e04e-4488-ad2f-db9cf6415e72	\N	{}	\N	f	f
7380e09c-b9d4-480c-8d2f-24de00ee1fa4	documents	attachments/organization_registration/3/j8x7VxJZhKZKXTRchW9rMrodWZ3GzozGB3Bl0VAT.pdf	\N	2026-08-18 08:54:00.435226+00	2026-08-18 08:54:00.435226+00	2026-08-18 08:54:00.435226+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:00.433Z", "contentLength": 0, "httpStatusCode": 200}	82940785-3d7c-4271-9f43-4c268d0e5843	\N	{}	\N	f	f
2aabeac3-fe8e-4d73-abed-8f64454b7f7c	documents	attachments/organization_registration/3/4TbQHRIUg6UqPAAklGxpiiDbJXoNoyLXDw83Ywmt.pdf	\N	2026-08-18 08:54:01.287401+00	2026-08-18 08:54:01.287401+00	2026-08-18 08:54:01.287401+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:01.285Z", "contentLength": 0, "httpStatusCode": 200}	05b8f5fe-2517-470b-b29b-038ffe703888	\N	{}	\N	f	f
4fb97ae4-8306-4c55-9c59-aa35fafa2b77	documents	attachments/organization_registration/3/etfGKxRcIUotwuHCLnmrCCpHrOJNS5EdgqZtHSkv.pdf	\N	2026-08-18 08:54:02.207072+00	2026-08-18 08:54:02.207072+00	2026-08-18 08:54:02.207072+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:02.205Z", "contentLength": 0, "httpStatusCode": 200}	578f3e89-f125-4d7d-8a60-999b4a7d6187	\N	{}	\N	f	f
f46a62de-e03f-46bd-a6ed-5865d13bd5bc	documents	attachments/organization_renewal/17/AKCri4opHFbmTeUMY9zTRbXGMsACRVTBFQUWGieJ.pdf	\N	2026-08-18 08:55:18.128388+00	2026-08-18 08:55:18.128388+00	2026-08-18 08:55:18.128388+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:18.126Z", "contentLength": 0, "httpStatusCode": 200}	2f14fe5b-11a1-4d40-b958-5cb09a1f3cd1	\N	{}	\N	f	f
765c7a5f-dfa7-4fb4-b4b4-91279496e4ab	documents	attachments/organization_registration/3/gCyP5yzBZXHKK0NHVSjdyg9zodD3nvtKs40NwZgG.pdf	\N	2026-08-18 08:54:03.115795+00	2026-08-18 08:54:03.115795+00	2026-08-18 08:54:03.115795+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:03.114Z", "contentLength": 0, "httpStatusCode": 200}	241bb1d0-bd4a-4210-8147-eeaddcb19e83	\N	{}	\N	f	f
101f980a-b851-468e-8699-8297af95ba36	documents	attachments/organization_renewal/48/KARaswVEucRTJgezRAoXLJMVxU28EdKrUEKfAtpZ.pdf	\N	2026-08-21 06:22:27.652714+00	2026-08-21 06:22:27.652714+00	2026-08-21 06:22:27.652714+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:27.647Z", "contentLength": 0, "httpStatusCode": 200}	36b5a41c-0237-43db-aa8f-67bde6a6d2b6	\N	{}	\N	f	f
51f80340-0103-43c4-b68f-08896eb5df4c	documents	attachments/organization_registration/4/sR5bQ3ShoNLHjRSHLGHIaqQ6fX4viUWmlUfcL1WR.pdf	\N	2026-08-18 08:54:04.022481+00	2026-08-18 08:54:04.022481+00	2026-08-18 08:54:04.022481+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:04.020Z", "contentLength": 0, "httpStatusCode": 200}	41747f3a-4086-4ae3-8904-aea1bf0dcf7a	\N	{}	\N	f	f
63b37480-79e1-4eef-ae24-d82417157172	documents	attachments/after_activity_report/33/Kt88Ud77OOTwf6KpoQdPvSYzYd3piR4e4VwzhqzS.jpg	\N	2026-08-18 08:55:19.098342+00	2026-08-18 08:55:19.098342+00	2026-08-18 08:55:19.098342+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "image/jpeg", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:19.096Z", "contentLength": 0, "httpStatusCode": 200}	17cb4001-1119-4c9a-8120-a6ebbd01aa44	\N	{}	\N	f	f
9a1eaf48-58d4-4dc5-a46b-49c08786cbf0	documents	attachments/organization_registration/4/lKoixNIK3rKNa8eD80EN2dtu1dI30YsSngaAgdhR.pdf	\N	2026-08-18 08:54:04.905994+00	2026-08-18 08:54:04.905994+00	2026-08-18 08:54:04.905994+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:04.904Z", "contentLength": 0, "httpStatusCode": 200}	73cc5163-1d00-4372-b109-020669810f72	\N	{}	\N	f	f
6f9462ef-0a47-4544-bcd3-443d558d0955	documents	attachments/organization_registration/4/aHWCilibNActT9bOvtja49xGan0i8JEWabkDEER8.pdf	\N	2026-08-18 08:54:05.741937+00	2026-08-18 08:54:05.741937+00	2026-08-18 08:54:05.741937+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:05.740Z", "contentLength": 0, "httpStatusCode": 200}	1f6ee082-1c5c-4980-80fa-085b12ae56b3	\N	{}	\N	f	f
dbec54fa-8eaa-48a8-97ea-db63247f23b5	documents	attachments/after_activity_report/33/BhFIoZtoCXAe0SPJWxodp2rcTKsXmpo1rWJhYinI.jpg	\N	2026-08-18 08:55:19.939231+00	2026-08-18 08:55:19.939231+00	2026-08-18 08:55:19.939231+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "image/jpeg", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:19.937Z", "contentLength": 0, "httpStatusCode": 200}	03dd8eba-e2cf-4d32-ae46-3142584caa4a	\N	{}	\N	f	f
b2a23be6-e3dc-4320-8513-9ecc4eb089be	documents	attachments/organization_registration/4/2TBDvzTKL5M0EhdFezxEbwAB72yR1mJGPBUsSdXY.pdf	\N	2026-08-18 08:54:06.597576+00	2026-08-18 08:54:06.597576+00	2026-08-18 08:54:06.597576+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:06.595Z", "contentLength": 0, "httpStatusCode": 200}	50ef921f-387e-4a84-b110-f64d9f7089a5	\N	{}	\N	f	f
bee8ad3b-1f6f-45ef-9595-41362e4d804c	documents	attachments/organization_registration/4/dAXITx83HiwoXte3iUHT4EIY9NPiNy0aulwqqZ5o.pdf	\N	2026-08-18 08:54:07.751321+00	2026-08-18 08:54:07.751321+00	2026-08-18 08:54:07.751321+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:07.749Z", "contentLength": 0, "httpStatusCode": 200}	3dfc604c-91a4-45d8-893c-bf69b629882c	\N	{}	\N	f	f
4443c967-be2f-439c-8753-ed52e6635a16	documents	attachments/after_activity_report/33/VYWlCw87aurpr2OGJ1HsGXNM5gsBtsOath2WiGtd.pdf	\N	2026-08-18 08:55:20.854363+00	2026-08-18 08:55:20.854363+00	2026-08-18 08:55:20.854363+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:20.852Z", "contentLength": 0, "httpStatusCode": 200}	8c321fb5-39f4-4d0f-80e8-122d259027da	\N	{}	\N	f	f
81cc41b6-c88e-42d2-80bc-7043c3437cb5	documents	attachments/organization_registration/4/S04hSj8MKsCezv3xG73zLY6OVy0VjxL2zlHaHoAH.pdf	\N	2026-08-18 08:54:08.633286+00	2026-08-18 08:54:08.633286+00	2026-08-18 08:54:08.633286+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:08.631Z", "contentLength": 0, "httpStatusCode": 200}	b09da26f-8331-496d-a9b7-dfd75a28fe65	\N	{}	\N	f	f
99715264-c38c-4a89-b7fc-38722fa1c9be	documents	attachments/organization_registration/5/qejW6qVCCitkV1CdaMtxBJiLc6Ox5WTCavNtrS5y.pdf	\N	2026-08-18 08:54:09.500722+00	2026-08-18 08:54:09.500722+00	2026-08-18 08:54:09.500722+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:09.498Z", "contentLength": 0, "httpStatusCode": 200}	5af9a2cb-3b91-4553-8a6a-89601507442f	\N	{}	\N	f	f
bc6283b1-d960-4124-866d-092acc8bc5d1	documents	attachments/organization_registration/5/JlV7bBBXeK7ijyDE4ZIagyT3mOrFAynJ7Djszx1o.pdf	\N	2026-08-18 08:54:10.48736+00	2026-08-18 08:54:10.48736+00	2026-08-18 08:54:10.48736+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:10.485Z", "contentLength": 0, "httpStatusCode": 200}	2d19b62c-f282-4d42-9123-afe1219e2271	\N	{}	\N	f	f
cf358950-9f74-4dba-8273-4a5c883c5a2f	documents	attachments/organization_renewal/48/YZB8323P6CfZs56G2jC4Lo5W9K2T1FEAM98gmMsp.pdf	\N	2026-08-21 06:22:29.876656+00	2026-08-21 06:22:29.876656+00	2026-08-21 06:22:29.876656+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:29.871Z", "contentLength": 0, "httpStatusCode": 200}	9beae676-3802-49dd-ac40-a772eeaf3645	\N	{}	\N	f	f
92e3cd19-fd70-42b8-9fd3-78564c18b464	documents	attachments/organization_registration/5/DhgCS7ALvdDfK8OtBLgOuL2N7WAe54Fa26KkaX1R.pdf	\N	2026-08-18 08:54:11.329507+00	2026-08-18 08:54:11.329507+00	2026-08-18 08:54:11.329507+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:11.327Z", "contentLength": 0, "httpStatusCode": 200}	a023552e-8cff-4bcf-8fe9-6499287ad10e	\N	{}	\N	f	f
9c90873c-0dd4-478b-8612-e3c729ad826f	documents	attachments/after_activity_report/33/gfVhu3b2eBuJSM5TYVaRHZXMnfuxmRGeQYWGz4CN.pdf	\N	2026-08-18 08:55:21.734865+00	2026-08-18 08:55:21.734865+00	2026-08-18 08:55:21.734865+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:21.733Z", "contentLength": 0, "httpStatusCode": 200}	ddcf7343-98dc-4887-9c28-31668d636088	\N	{}	\N	f	f
19ded2e4-f51a-4ff9-840b-98d2177d8758	documents	attachments/organization_registration/5/LnAtnGL7u95lHazVlB51QqUu6WHFpXyHheJDnNGr.pdf	\N	2026-08-18 08:54:12.178506+00	2026-08-18 08:54:12.178506+00	2026-08-18 08:54:12.178506+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:12.176Z", "contentLength": 0, "httpStatusCode": 200}	906442f5-b970-4c82-8e04-fdb114cd13f0	\N	{}	\N	f	f
43922741-e74b-47f5-8418-4b4731823c9e	documents	attachments/organization_registration/5/BUs4KJ3IGMMq7nTgEyEWFkChD86bJmX9dttkk92A.pdf	\N	2026-08-18 08:54:13.036024+00	2026-08-18 08:54:13.036024+00	2026-08-18 08:54:13.036024+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:13.033Z", "contentLength": 0, "httpStatusCode": 200}	c861c023-8cf8-4afa-9c2c-b8b09ec1378a	\N	{}	\N	f	f
ba9e6f4b-9c27-4343-91c7-d95e4b696b61	documents	attachments/after_activity_report/34/KaKPrxNqBUhD9cmwKD84Izmk8PlpTfWHH6Ij0xUj.jpg	\N	2026-08-18 08:55:22.579813+00	2026-08-18 08:55:22.579813+00	2026-08-18 08:55:22.579813+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "image/jpeg", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:22.578Z", "contentLength": 0, "httpStatusCode": 200}	c30c6b29-7bb7-45c7-aa68-646c4d667684	\N	{}	\N	f	f
9cd1c0c1-2ee9-4b8f-87cc-6b6119fed7eb	documents	attachments/organization_registration/5/uCkFyM07s2wi2F0Bz0bKECG8a9leUaBNNmbBq4VZ.pdf	\N	2026-08-18 08:54:13.871848+00	2026-08-18 08:54:13.871848+00	2026-08-18 08:54:13.871848+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:13.870Z", "contentLength": 0, "httpStatusCode": 200}	bad1ea3c-8834-4635-8d8d-9031415f9fe9	\N	{}	\N	f	f
cdf5925c-d9f3-456d-b857-565a8795440f	documents	attachments/organization_registration/6/dYqzRjmJ0SwQiYADYB3XoTXRw8tbpfWoXigfWqpr.pdf	\N	2026-08-18 08:54:14.781812+00	2026-08-18 08:54:14.781812+00	2026-08-18 08:54:14.781812+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:14.779Z", "contentLength": 0, "httpStatusCode": 200}	caf2ae9b-70b5-492a-820e-3428fc0c2f47	\N	{}	\N	f	f
3a0538b1-44e1-4ce6-a737-cae49322a1c5	documents	attachments/after_activity_report/34/MUFjnbAlnqa0WAMkf0ogz3uIELQB2WoDsgU0DxLF.jpg	\N	2026-08-18 08:55:23.694214+00	2026-08-18 08:55:23.694214+00	2026-08-18 08:55:23.694214+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "image/jpeg", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:23.692Z", "contentLength": 0, "httpStatusCode": 200}	6914f719-42f0-4599-9829-5a7b452fc9df	\N	{}	\N	f	f
a655d16d-6a4d-407a-8b4a-9158f1e66335	documents	attachments/organization_registration/6/geshict1FvQ7PRannoHSIJVcsa73b1rspUrGOVP6.pdf	\N	2026-08-18 08:54:15.625389+00	2026-08-18 08:54:15.625389+00	2026-08-18 08:54:15.625389+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:15.623Z", "contentLength": 0, "httpStatusCode": 200}	688d4cf5-ccdc-4ff1-9fad-131e5f68f3a8	\N	{}	\N	f	f
5c1dee4e-7957-4ffa-92d2-55a36e7c2c10	documents	attachments/organization_registration/6/9R2iai1DkpIKBPskStGfTpj6k2EEtyv6LncSCKzG.pdf	\N	2026-08-18 08:54:16.676064+00	2026-08-18 08:54:16.676064+00	2026-08-18 08:54:16.676064+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:16.674Z", "contentLength": 0, "httpStatusCode": 200}	c1ed819e-4d33-41b4-8b9c-b2829a28007b	\N	{}	\N	f	f
455787f7-6bcd-4e68-addb-1d152ebee4a9	documents	attachments/organization_registration/6/unTjVs74NIaXojtnG9xUAboePcC15i8TBoJ0yknx.pdf	\N	2026-08-18 08:54:17.65184+00	2026-08-18 08:54:17.65184+00	2026-08-18 08:54:17.65184+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:17.649Z", "contentLength": 0, "httpStatusCode": 200}	98115c58-f245-4a18-974e-4f4111182901	\N	{}	\N	f	f
42094557-d089-45fc-be96-5a3e4b30943b	documents	attachments/organization_renewal/48/8DI9iYT7d7bLv427tR9fnXXtUoqEWGZ8rhODS9lF.pdf	\N	2026-08-21 06:22:32.113056+00	2026-08-21 06:22:32.113056+00	2026-08-21 06:22:32.113056+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:32.108Z", "contentLength": 0, "httpStatusCode": 200}	902b9891-18cd-4d2f-b68e-475a5f6ccd70	\N	{}	\N	f	f
42264a1c-7536-4fa8-aef3-c150bd71d38c	documents	attachments/organization_renewal/49/MABe2GFboZu8rwlgBUENjWKqLqB9hBtjF4BJSB0Q.pdf	\N	2026-08-21 06:22:53.133393+00	2026-08-21 06:22:53.133393+00	2026-08-21 06:22:53.133393+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:53.128Z", "contentLength": 0, "httpStatusCode": 200}	ac0a0d25-225a-4fbb-8dac-cb71e3afb596	\N	{}	\N	f	f
86d97a16-8820-4a65-84b6-e30aeb0d4e16	documents	attachments/organization_registration/6/TurAn4Uw0aJ6mjZmydsdxrgy4fhmfGZUDYo6dU5c.pdf	\N	2026-08-18 08:54:18.594845+00	2026-08-18 08:54:18.594845+00	2026-08-18 08:54:18.594845+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:18.592Z", "contentLength": 0, "httpStatusCode": 200}	ca291021-9088-4d43-852f-b7ac17af0d79	\N	{}	\N	f	f
499e7ac0-0d28-4aae-b9ce-daf10607a8e7	documents	attachments/after_activity_report/34/h2js5Ku2bcRCSIiuA0Z8l76e4gDsJgfYxPzQXx8Q.pdf	\N	2026-08-18 08:55:24.598395+00	2026-08-18 08:55:24.598395+00	2026-08-18 08:55:24.598395+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:24.596Z", "contentLength": 0, "httpStatusCode": 200}	1eb3fcd3-389e-4149-ae27-84933bcf8ee8	\N	{}	\N	f	f
0d92960b-c765-42fa-875f-b0f820a5f04d	documents	attachments/organization_registration/6/UDhNs7kdf2PCnoRNfJkYOvsEaY292lJmgVx0MNMq.pdf	\N	2026-08-18 08:54:19.536542+00	2026-08-18 08:54:19.536542+00	2026-08-18 08:54:19.536542+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:19.534Z", "contentLength": 0, "httpStatusCode": 200}	75752167-f13b-4088-bdab-77ffa47c187f	\N	{}	\N	f	f
e03c2048-192b-43b1-9f91-33a3121de849	documents	attachments/organization_registration/7/XEZY9tXH4pJ3tcc8ND5GReWmWyiM6CV96pg5Zvhf.pdf	\N	2026-08-18 08:54:20.387154+00	2026-08-18 08:54:20.387154+00	2026-08-18 08:54:20.387154+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:20.385Z", "contentLength": 0, "httpStatusCode": 200}	1656bca4-0835-4829-98c3-555c4acde88c	\N	{}	\N	f	f
b93065d8-65c9-4456-b691-82933792641d	documents	attachments/after_activity_report/34/ibRcdp24JGcdpvH0EykcakscZBZrC4HLh1iOzrTF.pdf	\N	2026-08-18 08:55:25.478014+00	2026-08-18 08:55:25.478014+00	2026-08-18 08:55:25.478014+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:25.476Z", "contentLength": 0, "httpStatusCode": 200}	92e898ea-dfa4-490f-9707-4261d9543850	\N	{}	\N	f	f
a0631867-3cc7-4625-9cb1-a2123def6209	documents	attachments/organization_registration/7/sTnuUzugkhurmEhYWEDiix9ump5VREubaFg1ebRq.pdf	\N	2026-08-18 08:54:21.318289+00	2026-08-18 08:54:21.318289+00	2026-08-18 08:54:21.318289+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:21.316Z", "contentLength": 0, "httpStatusCode": 200}	d2026053-d232-440c-a651-404eb31e6715	\N	{}	\N	f	f
26f28e6a-25a5-4bb0-b6f0-bd725a689ecb	documents	attachments/organization_renewal/48/BH6nEWo0k5G1Ihq5NyUDhFFZIwJ9RYntgIf1qmew.pdf	\N	2026-08-21 06:22:34.4161+00	2026-08-21 06:22:34.4161+00	2026-08-21 06:22:34.4161+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:34.411Z", "contentLength": 0, "httpStatusCode": 200}	f45ba3a4-8b74-4c16-83bc-339f728571b2	\N	{}	\N	f	f
b7c96245-aceb-4e44-b1b2-8bb53b1e52d5	documents	attachments/organization_registration/7/p4VZVm4k9N0xXQLM2HUBZOsp5ncxkdpw9y7iH7yn.pdf	\N	2026-08-18 08:54:22.19925+00	2026-08-18 08:54:22.19925+00	2026-08-18 08:54:22.19925+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:22.197Z", "contentLength": 0, "httpStatusCode": 200}	d3abc266-1fd8-4966-bb90-b3d87855855d	\N	{}	\N	f	f
4450770f-25c4-4e50-8a25-89815a2660d4	documents	attachments/after_activity_report/35/Fp7zQmJFxRaZXdHAwsUZulPWCPNZLFphmCpXUmnQ.jpg	\N	2026-08-18 08:55:26.338399+00	2026-08-18 08:55:26.338399+00	2026-08-18 08:55:26.338399+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "image/jpeg", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:26.336Z", "contentLength": 0, "httpStatusCode": 200}	58e942f5-7cc7-4e84-a772-1a88791460f1	\N	{}	\N	f	f
36c1dea5-a36a-4d41-8b29-77e4de1e9afa	documents	attachments/organization_registration/7/YZC5m471C43BGMFeZgsjVuXNKbEi0wCLn6RZ0hFz.pdf	\N	2026-08-18 08:54:23.028937+00	2026-08-18 08:54:23.028937+00	2026-08-18 08:54:23.028937+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:23.027Z", "contentLength": 0, "httpStatusCode": 200}	88e18455-f771-428d-959f-654e6d6fd878	\N	{}	\N	f	f
62f7860c-fb49-48eb-a627-e802ef440ec7	documents	attachments/organization_registration/7/zgaLdrLMGHMFPOEqGuoetz1dgeilfZg2tgJmYJu7.pdf	\N	2026-08-18 08:54:23.867097+00	2026-08-18 08:54:23.867097+00	2026-08-18 08:54:23.867097+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:23.865Z", "contentLength": 0, "httpStatusCode": 200}	938e67a4-6c83-42e8-9e7a-faad15171767	\N	{}	\N	f	f
cd61d60c-d4d7-4392-bd3e-bd428347522b	documents	attachments/organization_registration/7/GNOagnbpgtCUh2zO1v9HnIg5FiH45ET1uvbJ82sk.pdf	\N	2026-08-18 08:54:24.735316+00	2026-08-18 08:54:24.735316+00	2026-08-18 08:54:24.735316+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:24.733Z", "contentLength": 0, "httpStatusCode": 200}	b08f4b4e-920e-4b22-8c88-4b622bf5debb	\N	{}	\N	f	f
5c8ac157-7b3b-4237-a892-d6a24278e0bd	documents	attachments/organization_registration/8/7XUHzjY7UCdNX9RQCf2N7COlyCrB8gvu3ioS5TdX.pdf	\N	2026-08-18 08:54:25.578296+00	2026-08-18 08:54:25.578296+00	2026-08-18 08:54:25.578296+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:25.576Z", "contentLength": 0, "httpStatusCode": 200}	7089657b-1732-458b-a218-544972fa5c7f	\N	{}	\N	f	f
c73ee292-38ae-494c-b7ee-f50c8d971c01	documents	attachments/after_activity_report/35/L8zMYhs3rn6WEXGTDJyvFHUgnaPpg1eVURYN6WcQ.jpg	\N	2026-08-18 08:55:27.202991+00	2026-08-18 08:55:27.202991+00	2026-08-18 08:55:27.202991+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "image/jpeg", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:27.200Z", "contentLength": 0, "httpStatusCode": 200}	4e7a090c-6c2c-4dc6-b1ad-90c96f94bc33	\N	{}	\N	f	f
8c0f4cfb-545f-459a-a136-138d65f41ca5	documents	attachments/organization_registration/8/h7SzPvzYiga2yIIrSwaHi94TnUSLTVdI2kn7UXBJ.pdf	\N	2026-08-18 08:54:26.484708+00	2026-08-18 08:54:26.484708+00	2026-08-18 08:54:26.484708+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:26.482Z", "contentLength": 0, "httpStatusCode": 200}	f04df670-33b7-43b9-9704-420c917902d3	\N	{}	\N	f	f
31f58e8d-aff2-4b73-b1be-a317d24b394a	documents	attachments/organization_registration/8/EWe1XXMHz8Yi54v6Mo5qmWsgxZCGM9xcemz5Q0op.pdf	\N	2026-08-18 08:54:27.333809+00	2026-08-18 08:54:27.333809+00	2026-08-18 08:54:27.333809+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:27.331Z", "contentLength": 0, "httpStatusCode": 200}	5a78fcbd-12e4-4103-a279-d85c21bb2ffe	\N	{}	\N	f	f
b867c5e9-8c51-4c78-b54b-dd2750227f44	documents	attachments/after_activity_report/35/hsoTytGRYg7mKOvV0NSN2ZEGnJTEVw9cw7DIQQZV.pdf	\N	2026-08-18 08:55:28.050973+00	2026-08-18 08:55:28.050973+00	2026-08-18 08:55:28.050973+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:28.049Z", "contentLength": 0, "httpStatusCode": 200}	62fd7f6f-ee41-45d3-bdfd-de6b347f24ca	\N	{}	\N	f	f
00370cbc-f8d2-4327-b50a-195cf06f8b92	documents	attachments/organization_registration/8/2pa2O9ykGeEF7ZdR56uWW7XIbghKoTAns3au5FAZ.pdf	\N	2026-08-18 08:54:28.187191+00	2026-08-18 08:54:28.187191+00	2026-08-18 08:54:28.187191+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:28.185Z", "contentLength": 0, "httpStatusCode": 200}	71c3c379-e1a6-4d0a-be37-fe92e70d938f	\N	{}	\N	f	f
c3a817e3-22cc-4fe9-a5d9-86278fbb6ac9	documents	attachments/organization_renewal/48/gRrmeJ0Ysn43XSAOrdpP2wvCQP4hMZIbS7gB1Zqu.pdf	\N	2026-08-21 06:22:37.012308+00	2026-08-21 06:22:37.012308+00	2026-08-21 06:22:37.012308+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:37.007Z", "contentLength": 0, "httpStatusCode": 200}	d9e4387f-db36-496e-9471-9afec10d2112	\N	{}	\N	f	f
f118d2db-2587-42fc-84d5-03b55dd00d84	documents	attachments/organization_registration/8/OJkzNeXZLhVYLTTFPChgNjEnKHutFZDlPDg2GCcM.pdf	\N	2026-08-18 08:54:29.033411+00	2026-08-18 08:54:29.033411+00	2026-08-18 08:54:29.033411+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:29.031Z", "contentLength": 0, "httpStatusCode": 200}	0627cb79-5ea0-42d1-b1b4-800e3a1c4bcc	\N	{}	\N	f	f
8a158084-33bd-4448-ba21-789eaa15c9c3	documents	attachments/after_activity_report/35/YUVl4Oy7kLLdN0qWSEEYmtyohNWVwbFzqgYdvGOw.pdf	\N	2026-08-18 08:55:28.916129+00	2026-08-18 08:55:28.916129+00	2026-08-18 08:55:28.916129+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:28.914Z", "contentLength": 0, "httpStatusCode": 200}	67e1d5dc-9881-4a1f-82ac-0aa6610db1f1	\N	{}	\N	f	f
ebfb00ff-fe7d-4ba0-8c41-49264e0ec846	documents	attachments/organization_registration/8/l5IFd9ITRpMleUZgcdDMHRX6pTmaATtxWNrT03gq.pdf	\N	2026-08-18 08:54:29.874579+00	2026-08-18 08:54:29.874579+00	2026-08-18 08:54:29.874579+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:29.872Z", "contentLength": 0, "httpStatusCode": 200}	2042c9a7-4eb3-4de0-8dea-4363c64d1667	\N	{}	\N	f	f
ff657474-40dd-4c98-90a5-54a841c41185	documents	attachments/organization_registration/10/VZxAawCY2hoUQUbceupjqGB74PTZJqWqg15Ka5ud.pdf	\N	2026-08-18 08:54:30.722365+00	2026-08-18 08:54:30.722365+00	2026-08-18 08:54:30.722365+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:30.720Z", "contentLength": 0, "httpStatusCode": 200}	45a13de8-3eac-40bd-bd5b-70cd7f837103	\N	{}	\N	f	f
0f7bf913-9066-4eae-a12f-3694cfa83222	documents	attachments/organization_registration/10/Afrs7qjNQOK1cBlYoVwJT5AWmsFUCAtnBq8NsTPL.pdf	\N	2026-08-18 08:54:31.552113+00	2026-08-18 08:54:31.552113+00	2026-08-18 08:54:31.552113+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:31.550Z", "contentLength": 0, "httpStatusCode": 200}	031f7c36-7f5e-4c60-a1c8-b06b124204ee	\N	{}	\N	f	f
24643f63-aa04-4451-a7d6-7607e4099c8e	documents	attachments/organization_registration/10/lc3xN8tEzZbrnTdd14ZjX0BU9yF8olsWyA3ZstSv.pdf	\N	2026-08-18 08:54:32.392019+00	2026-08-18 08:54:32.392019+00	2026-08-18 08:54:32.392019+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:32.390Z", "contentLength": 0, "httpStatusCode": 200}	4ce8d712-5aea-4d96-aa49-5393e59f62c9	\N	{}	\N	f	f
4e949a2e-ca02-4f24-983a-abf32cd29961	documents	attachments/organization_registration/10/trFLNVV0KD4JvqhtaudPa2nPnjowi9jwN8vlTpgE.pdf	\N	2026-08-18 08:54:33.243829+00	2026-08-18 08:54:33.243829+00	2026-08-18 08:54:33.243829+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:33.241Z", "contentLength": 0, "httpStatusCode": 200}	13aedee6-0608-4b66-8e45-77b24addcab0	\N	{}	\N	f	f
a2525c0d-e475-4cbf-ae25-74d8401716ad	documents	attachments/organization_registration/35/FVsK8l0jbJy8u2FPlzx0c9WpoiPZnOf8i0keiSeO.pdf	\N	2026-08-21 06:19:50.249809+00	2026-08-21 06:19:50.249809+00	2026-08-21 06:19:50.249809+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:19:50.244Z", "contentLength": 0, "httpStatusCode": 200}	1c51a149-0a8e-40ed-80c5-597b181e7bd5	\N	{}	\N	f	f
357d4811-c0b0-4472-92b2-38d65f527537	documents	attachments/organization_registration/10/CG414qaAzrYLzpAmlSm4eqMmB5peuJAEOZrgW7ik.pdf	\N	2026-08-18 08:54:34.103211+00	2026-08-18 08:54:34.103211+00	2026-08-18 08:54:34.103211+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:34.101Z", "contentLength": 0, "httpStatusCode": 200}	79901b19-749f-4d7b-a6f2-5a424d6b0d80	\N	{}	\N	f	f
a5f1b837-884b-4cf0-9a13-86b0114320d1	documents	attachments/organization_renewal/48/2PHmemdkpOKID5jZDZkErEYgFj4Stu0N5K5bwpqV.pdf	\N	2026-08-21 06:22:39.349189+00	2026-08-21 06:22:39.349189+00	2026-08-21 06:22:39.349189+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:39.344Z", "contentLength": 0, "httpStatusCode": 200}	eef34b71-b135-425c-bcd7-13a141af5f19	\N	{}	\N	f	f
f372566b-2fa3-4dfb-bf36-bd8b01aa9ace	documents	attachments/organization_registration/10/1pY5xaYm27GZJ1FKTyBqmaPpVxWT6JqvNagbgNU5.pdf	\N	2026-08-18 08:54:34.961751+00	2026-08-18 08:54:34.961751+00	2026-08-18 08:54:34.961751+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:34.959Z", "contentLength": 0, "httpStatusCode": 200}	0f7b2d32-de57-417d-8689-40ef8acb6016	\N	{}	\N	f	f
5991e26a-117a-4bff-a5c3-830a335dea9c	documents	attachments/organization_registration/35/TqV8CTzz3mJc15UJuqLXfOzYQxviVFvI6clcpfSt.pdf	\N	2026-08-21 06:19:52.832993+00	2026-08-21 06:19:52.832993+00	2026-08-21 06:19:52.832993+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:19:52.828Z", "contentLength": 0, "httpStatusCode": 200}	e7cb1da4-fc30-4571-bd43-a588e7d435ba	\N	{}	\N	f	f
d6a26de0-a8bf-4724-a6e7-596d8a7daf45	documents	attachments/organization_registration/11/LrNXvg3BeFmf9Q2c4eqPi35XIdcxZf3z7bWvjDUl.pdf	\N	2026-08-18 08:54:35.799054+00	2026-08-18 08:54:35.799054+00	2026-08-18 08:54:35.799054+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:35.797Z", "contentLength": 0, "httpStatusCode": 200}	c80cc10b-5a93-4378-8d5f-ea329e3da41d	\N	{}	\N	f	f
2695ea91-2566-40bb-b9bb-0faf29541766	documents	attachments/organization_registration/38/L7oMDxxKCyW6S7s04vsxrMMBC3QSYLnu6Pl3ItIy.pdf	\N	2026-08-21 06:20:42.51088+00	2026-08-21 06:20:42.51088+00	2026-08-21 06:20:42.51088+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:42.506Z", "contentLength": 0, "httpStatusCode": 200}	b6b9fc80-7bf4-4f24-bfdf-bc56f575909b	\N	{}	\N	f	f
3d97cd2c-e769-4206-85ac-d9680510742b	documents	attachments/organization_registration/11/gm1jFT09wInUmwjgp11dLuwvZ25Dsi5wH88IF5Bm.pdf	\N	2026-08-18 08:54:36.664273+00	2026-08-18 08:54:36.664273+00	2026-08-18 08:54:36.664273+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:36.662Z", "contentLength": 0, "httpStatusCode": 200}	bf9cecf6-a2d3-4da6-a106-4b7c1d3d890a	\N	{}	\N	f	f
b09206c8-f97d-4970-b18e-6e4c5f12c2f2	documents	attachments/organization_registration/11/YUzydw4D3t6NKNqzD5kSmpqptScIxcxhkIZTJu67.pdf	\N	2026-08-18 08:54:37.531097+00	2026-08-18 08:54:37.531097+00	2026-08-18 08:54:37.531097+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:37.529Z", "contentLength": 0, "httpStatusCode": 200}	75769cd4-6b75-4502-97b1-25920f8d79f0	\N	{}	\N	f	f
9f738658-9e93-409d-8e2a-a9f01f0f0033	documents	attachments/organization_registration/38/vVZCSFVLm30xh1ImxP8FnA086wiki0eDdK89xbok.pdf	\N	2026-08-21 06:20:44.901637+00	2026-08-21 06:20:44.901637+00	2026-08-21 06:20:44.901637+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:44.896Z", "contentLength": 0, "httpStatusCode": 200}	e64a7d6f-1366-4152-b8ea-1b5ad9f8afb6	\N	{}	\N	f	f
46da6537-d7dd-4f6b-ba35-f4f6cb5e436a	documents	attachments/organization_registration/11/CMe3817dApL2UMIIS4xcd64GFQRV5ZB2zAswO5eR.pdf	\N	2026-08-18 08:54:38.372468+00	2026-08-18 08:54:38.372468+00	2026-08-18 08:54:38.372468+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:38.370Z", "contentLength": 0, "httpStatusCode": 200}	5891f57e-cabb-4d57-b651-074680a62aff	\N	{}	\N	f	f
a796a906-c947-4211-bb18-9bf000e8c0c5	documents	attachments/organization_registration/11/x11qTW9GYpI3qEqxSqDjX01uXSmmjKgiopFgqX0K.pdf	\N	2026-08-18 08:54:39.281915+00	2026-08-18 08:54:39.281915+00	2026-08-18 08:54:39.281915+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:39.279Z", "contentLength": 0, "httpStatusCode": 200}	6672f1c0-b489-42d5-aad5-8098314c5e68	\N	{}	\N	f	f
88680324-bf3c-4ed7-99a6-d81c9c710585	documents	attachments/organization_registration/11/NhlQlR4QnHXBTmPVgBD10jQGrVfyYdhvRaz5CHd4.pdf	\N	2026-08-18 08:54:40.151727+00	2026-08-18 08:54:40.151727+00	2026-08-18 08:54:40.151727+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:40.149Z", "contentLength": 0, "httpStatusCode": 200}	7e60b23f-5e6c-42cd-a9fd-140dfe48fc7f	\N	{}	\N	f	f
06ef1553-764e-4914-a1f1-aca8ebfba74f	documents	attachments/organization_registration/12/isN3xhfWwb5kpcecFL0nPdscoVNAzn2CxAPO6eHl.pdf	\N	2026-08-18 08:54:41.003401+00	2026-08-18 08:54:41.003401+00	2026-08-18 08:54:41.003401+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:41.001Z", "contentLength": 0, "httpStatusCode": 200}	2db0dc71-3852-4ea6-b7f9-5f7b8790a858	\N	{}	\N	f	f
f17b9372-bd27-4415-8c80-778055cd6c5a	documents	attachments/organization_registration/35/z907YUpSu70EQjDuYvw0yV7AXGGz8u4yojv3c3g0.pdf	\N	2026-08-21 06:19:55.116973+00	2026-08-21 06:19:55.116973+00	2026-08-21 06:19:55.116973+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:19:55.112Z", "contentLength": 0, "httpStatusCode": 200}	223a954c-f15c-4cbe-b1a2-35540fbe8959	\N	{}	\N	f	f
a1741300-4083-4baa-a38a-73ae5e541b0e	documents	attachments/organization_registration/12/iRqbxCz8RZDhlmGOIj1jL1r1htoff0TtIFDQsn4r.pdf	\N	2026-08-18 08:54:41.942722+00	2026-08-18 08:54:41.942722+00	2026-08-18 08:54:41.942722+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:41.940Z", "contentLength": 0, "httpStatusCode": 200}	ad18d03a-c32d-4ba0-95b8-27af9ce6de80	\N	{}	\N	f	f
4e59dc45-a394-4b37-ae12-d33d7c45a987	documents	attachments/organization_renewal/48/W2C43RwCCmxiBayf17yGoapcDlPbrVCD5YlU6frQ.pdf	\N	2026-08-21 06:22:41.644427+00	2026-08-21 06:22:41.644427+00	2026-08-21 06:22:41.644427+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:41.639Z", "contentLength": 0, "httpStatusCode": 200}	c4cf10d9-fb24-4376-be2d-d14d9b3746b9	\N	{}	\N	f	f
85bbf6e8-d842-42df-ac6b-54a531c15620	documents	attachments/organization_registration/12/jKrC8YmlU98vKXzgY1IWXwktCxGntGLefPac2N5z.pdf	\N	2026-08-18 08:54:42.822481+00	2026-08-18 08:54:42.822481+00	2026-08-18 08:54:42.822481+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:42.820Z", "contentLength": 0, "httpStatusCode": 200}	20b57678-272c-4510-ab36-8ec7ca4144ed	\N	{}	\N	f	f
116e25a8-6853-4da1-aa51-401f4d5f46db	documents	attachments/organization_registration/35/GxzsuiA0Po9PbA9X9QxsJDZjGRJO53FeHY150Xlq.pdf	\N	2026-08-21 06:19:57.377875+00	2026-08-21 06:19:57.377875+00	2026-08-21 06:19:57.377875+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:19:57.373Z", "contentLength": 0, "httpStatusCode": 200}	217ba3de-4365-4521-ad55-6c816c708375	\N	{}	\N	f	f
0b4ceb79-2d72-4b14-a661-763e426824d6	documents	attachments/organization_registration/12/6RVz4Vql2eo9khaw7baPsiCIQLYWnPeUAktQUK4a.pdf	\N	2026-08-18 08:54:43.679473+00	2026-08-18 08:54:43.679473+00	2026-08-18 08:54:43.679473+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:43.677Z", "contentLength": 0, "httpStatusCode": 200}	b2e95fc2-cd24-4875-9268-1d61c690e630	\N	{}	\N	f	f
e8beefd8-e82f-4ad7-9fd2-3543b8bf32f1	documents	attachments/organization_registration/12/Z8a7yfNpTvTjlu1UHcHX746Dwf8v1IKJjTCez7aI.pdf	\N	2026-08-18 08:54:44.549676+00	2026-08-18 08:54:44.549676+00	2026-08-18 08:54:44.549676+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:44.547Z", "contentLength": 0, "httpStatusCode": 200}	11ebfdc9-6bce-43dc-bdd9-2e49e0fef3fe	\N	{}	\N	f	f
aef42027-5787-4ddd-96c5-2af983cbabde	documents	attachments/organization_registration/35/uUS9IxiUHhn4TiJFZNr2oAeigOKqCPGYEhu7YNal.pdf	\N	2026-08-21 06:19:59.68246+00	2026-08-21 06:19:59.68246+00	2026-08-21 06:19:59.68246+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:19:59.677Z", "contentLength": 0, "httpStatusCode": 200}	2b7b6e61-ac7f-4f90-84cc-c9404457849a	\N	{}	\N	f	f
46dd71bd-d816-4bfa-b0a3-a99682b0fa2a	documents	attachments/organization_registration/12/IkCWqaAMIQOqiM9uc8PNfsTy3X3re81C5YNqMyxT.pdf	\N	2026-08-18 08:54:45.49989+00	2026-08-18 08:54:45.49989+00	2026-08-18 08:54:45.49989+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:45.497Z", "contentLength": 0, "httpStatusCode": 200}	339f0d94-a6f0-4bc9-aa8b-cfa908b8c10f	\N	{}	\N	f	f
af4fac2f-6a48-4ce0-aa7f-8cab7065562a	documents	attachments/organization_renewal/14/pEDP9Gd3gw1r74QOB58Yc2q1MG2ch240grkKM4df.pdf	\N	2026-08-18 08:54:46.329257+00	2026-08-18 08:54:46.329257+00	2026-08-18 08:54:46.329257+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:46.327Z", "contentLength": 0, "httpStatusCode": 200}	e36b9277-ff3b-4b9c-addd-6c76de8f0608	\N	{}	\N	f	f
b9367429-d00b-4047-8822-8e09b658c050	documents	attachments/organization_renewal/14/L3UufFUu5yonKEngurBbQ8ZJ0mRsRpP4EglBsIaq.pdf	\N	2026-08-18 08:54:47.206599+00	2026-08-18 08:54:47.206599+00	2026-08-18 08:54:47.206599+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:47.204Z", "contentLength": 0, "httpStatusCode": 200}	c8122dda-e599-4e73-91a3-f700e99453b8	\N	{}	\N	f	f
d5fd296d-8206-4984-a5dc-2c338d3b96ec	documents	attachments/organization_renewal/14/rn7ZVAd6ro80nhyq19ADY6U284Gl9kbSOZDWdD3J.pdf	\N	2026-08-18 08:54:48.379701+00	2026-08-18 08:54:48.379701+00	2026-08-18 08:54:48.379701+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:48.377Z", "contentLength": 0, "httpStatusCode": 200}	605f9b6c-750d-42c3-92ed-f636344e2052	\N	{}	\N	f	f
97ede1a4-7f69-4637-8506-69369ea13dbe	documents	attachments/organization_registration/35/ambEKuI9N7vSaOrS1QsxOBp3RWjgwozi3AGMlW6L.pdf	\N	2026-08-21 06:20:01.93802+00	2026-08-21 06:20:01.93802+00	2026-08-21 06:20:01.93802+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:01.933Z", "contentLength": 0, "httpStatusCode": 200}	6eef83cb-1cc6-4249-a217-b4ae1c6ad6cf	\N	{}	\N	f	f
4a1a6d03-a49a-4fee-9d69-e801fa929545	documents	attachments/organization_renewal/14/HAIXNHcTmaZvVwvG8LjOmGhEsDnCjEGQH0MzPk8R.pdf	\N	2026-08-18 08:54:49.245877+00	2026-08-18 08:54:49.245877+00	2026-08-18 08:54:49.245877+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:49.243Z", "contentLength": 0, "httpStatusCode": 200}	b5c623a6-c186-411a-8d1c-597f56ffd777	\N	{}	\N	f	f
b52e9188-f929-4fef-b1e4-a0cca5928fb2	documents	attachments/organization_renewal/49/Pg89me6VW0ctVhj1LGyjGQOoh24SUElalj9lg5dP.pdf	\N	2026-08-21 06:22:43.880734+00	2026-08-21 06:22:43.880734+00	2026-08-21 06:22:43.880734+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:43.875Z", "contentLength": 0, "httpStatusCode": 200}	c63e2214-1320-444b-b8ca-1d4247607391	\N	{}	\N	f	f
8b62c1c1-1f5f-43ef-90b1-1e662f8579de	documents	attachments/organization_renewal/14/om1tTzLlvQqR0ByVfeUOR0jJM61qMWUDvJgTvT7o.pdf	\N	2026-08-18 08:54:50.090482+00	2026-08-18 08:54:50.090482+00	2026-08-18 08:54:50.090482+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:50.088Z", "contentLength": 0, "httpStatusCode": 200}	15190486-05d6-4720-a1d7-25a74b2a14e2	\N	{}	\N	f	f
60987139-f3d3-4ffb-866c-7d0402164007	documents	attachments/organization_registration/36/bzovJGzOv0wSRLqMXSppn7uuVQ8L9zC7dLp5LUCZ.pdf	\N	2026-08-21 06:20:04.259299+00	2026-08-21 06:20:04.259299+00	2026-08-21 06:20:04.259299+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:04.254Z", "contentLength": 0, "httpStatusCode": 200}	33037c2e-4140-4191-8973-4cd46d971f15	\N	{}	\N	f	f
27b1fee1-a5af-4880-abb2-705e105937b4	documents	attachments/organization_renewal/14/AiHrTG418bmf8Be9UX2Y8YX2yEHkxo9TWHmPlDMU.pdf	\N	2026-08-18 08:54:50.938326+00	2026-08-18 08:54:50.938326+00	2026-08-18 08:54:50.938326+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:50.936Z", "contentLength": 0, "httpStatusCode": 200}	120a265a-954b-44ca-bb28-3dd30438524e	\N	{}	\N	f	f
51d1947c-d800-49d9-892e-ac15aeaa33b7	documents	attachments/organization_renewal/14/Uga2NAv7FeIj5WLlID2jssrdwlM1ztbgC7S6GXYO.pdf	\N	2026-08-18 08:54:51.783297+00	2026-08-18 08:54:51.783297+00	2026-08-18 08:54:51.783297+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:51.781Z", "contentLength": 0, "httpStatusCode": 200}	da20d9f1-16da-4db3-96a0-eb0444555097	\N	{}	\N	f	f
41be7439-a1dc-460c-9fe8-531f83fb3d5d	documents	attachments/organization_registration/36/Ap7ZVPfNkmVOEG3W2cYBaUHVQkwcW6ZgUXryCeU6.pdf	\N	2026-08-21 06:20:06.544625+00	2026-08-21 06:20:06.544625+00	2026-08-21 06:20:06.544625+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:06.539Z", "contentLength": 0, "httpStatusCode": 200}	fd668594-2434-4db4-a841-26954abf5738	\N	{}	\N	f	f
a59e34d2-79aa-45f3-9c50-6bc75ffc2f7f	documents	attachments/organization_renewal/14/Qzh6ERti61mKhvlFszTodS5vd91iXBs9Gkk3oqf2.pdf	\N	2026-08-18 08:54:52.871234+00	2026-08-18 08:54:52.871234+00	2026-08-18 08:54:52.871234+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:52.869Z", "contentLength": 0, "httpStatusCode": 200}	c242203e-9a52-4780-92fa-f37f285bcb3b	\N	{}	\N	f	f
07d9efa9-e6de-49db-a04d-3628b76253a2	documents	attachments/organization_renewal/14/V5BJmmeN8nDnRS66KDkGiWXz7QxkMhaqNfObwmY6.pdf	\N	2026-08-18 08:54:53.699931+00	2026-08-18 08:54:53.699931+00	2026-08-18 08:54:53.699931+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:53.698Z", "contentLength": 0, "httpStatusCode": 200}	c8ae6f65-7f59-4e7e-be0c-e9e425508593	\N	{}	\N	f	f
457c6387-de6c-4c40-b191-16391bafd712	documents	attachments/organization_renewal/15/6Acvjfpwd910vxqY3F0xNnAZWrOKnJH7guPve8UY.pdf	\N	2026-08-18 08:54:54.548946+00	2026-08-18 08:54:54.548946+00	2026-08-18 08:54:54.548946+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:54.547Z", "contentLength": 0, "httpStatusCode": 200}	df0ac6a6-2d9f-46a0-8386-418dc67655e3	\N	{}	\N	f	f
c2928fef-757f-4f1a-b22b-5b75ca5406e6	documents	attachments/organization_renewal/15/zrRpSQpLsRAsyl2SaAQz68FRFr9tfI5v5yslOXhh.pdf	\N	2026-08-18 08:54:55.377544+00	2026-08-18 08:54:55.377544+00	2026-08-18 08:54:55.377544+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:55.375Z", "contentLength": 0, "httpStatusCode": 200}	1a717341-6edc-4edd-8a88-c91e8e832c07	\N	{}	\N	f	f
ce6b5241-4221-4029-b5bb-a256d97e9514	documents	attachments/organization_registration/36/IJc66MUIIP49tocZZa9qlxBqZgE6LVRd80fFt5Il.pdf	\N	2026-08-21 06:20:08.995819+00	2026-08-21 06:20:08.995819+00	2026-08-21 06:20:08.995819+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:08.990Z", "contentLength": 0, "httpStatusCode": 200}	6a16a91d-07d6-4bcf-b24b-272d78178027	\N	{}	\N	f	f
7f59972c-1f42-4baa-9be6-7c869d91af47	documents	attachments/organization_renewal/15/GslwHNeynRcDt9S56YCIrWz8A6hg9D41r54KvYvC.pdf	\N	2026-08-18 08:54:56.218486+00	2026-08-18 08:54:56.218486+00	2026-08-18 08:54:56.218486+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:56.216Z", "contentLength": 0, "httpStatusCode": 200}	1b3fb1b4-5667-48f1-940a-4e2b342aefc6	\N	{}	\N	f	f
2f5ef81b-3308-481e-9633-c96c0cef4708	documents	attachments/organization_renewal/49/Uemykzfx2ReCEEVl8xS0iPkacbLOj1DR2hoCVdxD.pdf	\N	2026-08-21 06:22:46.339795+00	2026-08-21 06:22:46.339795+00	2026-08-21 06:22:46.339795+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:46.335Z", "contentLength": 0, "httpStatusCode": 200}	1a1a88de-177e-4e44-a9c3-2e8398fc3470	\N	{}	\N	f	f
f3d4e80e-02c8-4e59-86b4-6130f9116d4c	documents	attachments/organization_renewal/15/GLTMgCjdduvZuIaN7GgZf7uvad8JhVf7qN8U2EjK.pdf	\N	2026-08-18 08:54:57.063494+00	2026-08-18 08:54:57.063494+00	2026-08-18 08:54:57.063494+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:57.061Z", "contentLength": 0, "httpStatusCode": 200}	533ab7e3-795b-43ed-9420-4b935eefbea6	\N	{}	\N	f	f
d748f594-9ba4-4a8e-aa04-7dfcc2bd7969	documents	attachments/organization_registration/36/DURVIodenFgoeQfM43W7pMVdARU7u1F1A3zwX85r.pdf	\N	2026-08-21 06:20:12.462384+00	2026-08-21 06:20:12.462384+00	2026-08-21 06:20:12.462384+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:12.457Z", "contentLength": 0, "httpStatusCode": 200}	217c1749-6db6-498d-9785-98a36a6120e7	\N	{}	\N	f	f
e6d47bdf-24a1-41ff-abaa-0023f52eb0d3	documents	attachments/organization_renewal/15/1Psj43cHskVVTzuB3l8D6uOixFAp7mZognqh2kFS.pdf	\N	2026-08-18 08:54:57.986963+00	2026-08-18 08:54:57.986963+00	2026-08-18 08:54:57.986963+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:57.985Z", "contentLength": 0, "httpStatusCode": 200}	b8395b78-1d30-455f-9468-c44d9d8f1d0f	\N	{}	\N	f	f
6a1e8b58-b2a9-473f-8f5c-03fc04157e0d	documents	attachments/organization_renewal/15/wphzCspFfLVuSmnmVeOv7CyQOaSB2dABjWFLiWxk.pdf	\N	2026-08-18 08:54:58.83697+00	2026-08-18 08:54:58.83697+00	2026-08-18 08:54:58.83697+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:58.835Z", "contentLength": 0, "httpStatusCode": 200}	c9fc8950-26e9-494a-b05f-c126f3ebc61d	\N	{}	\N	f	f
a68ec991-df18-4fc0-80e9-5e7cb469a41b	documents	attachments/organization_registration/36/h3tNiQvE0GJuVkx8hoeejk3mfr5C5zUglTs5Emlq.pdf	\N	2026-08-21 06:20:14.849214+00	2026-08-21 06:20:14.849214+00	2026-08-21 06:20:14.849214+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:14.844Z", "contentLength": 0, "httpStatusCode": 200}	8a924cb1-f752-4d44-9833-011cf6b0a610	\N	{}	\N	f	f
355f03a0-65a4-442a-a068-eba26889ab49	documents	attachments/organization_renewal/15/MzuR184X48xR7ccv5ZMvOyTvpB9BQhv8offsqigp.pdf	\N	2026-08-18 08:54:59.685731+00	2026-08-18 08:54:59.685731+00	2026-08-18 08:54:59.685731+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:54:59.683Z", "contentLength": 0, "httpStatusCode": 200}	42868c59-a437-447d-a9e8-c1226dc6d049	\N	{}	\N	f	f
6dc30c60-96b7-4b11-8c44-1b8465a1d752	documents	attachments/organization_renewal/15/FgIkewfUaMa7xuRnz6dStrt9ezfOeD1jLEB903Ep.pdf	\N	2026-08-18 08:55:00.553819+00	2026-08-18 08:55:00.553819+00	2026-08-18 08:55:00.553819+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:00.551Z", "contentLength": 0, "httpStatusCode": 200}	c4061b0a-15b3-47f7-a61d-ff83f71b2701	\N	{}	\N	f	f
bcef59a5-defe-484c-b76a-a81d94bfd2ec	documents	attachments/organization_renewal/15/UK3iQYpDU26B3A7pIizD8YuMdW4oWK2Dv3fgZmvj.pdf	\N	2026-08-18 08:55:01.415735+00	2026-08-18 08:55:01.415735+00	2026-08-18 08:55:01.415735+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:01.413Z", "contentLength": 0, "httpStatusCode": 200}	53ba0f13-e492-4a33-bca7-6dc466538d58	\N	{}	\N	f	f
ca7788f6-79b7-4b2a-9def-34c5c3d41990	documents	attachments/organization_renewal/16/4paHdUBOKZ0uG5sX0kLUYcciC5yy9hzY67K5qCPW.pdf	\N	2026-08-18 08:55:02.25426+00	2026-08-18 08:55:02.25426+00	2026-08-18 08:55:02.25426+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:02.252Z", "contentLength": 0, "httpStatusCode": 200}	47467b48-5471-4ad3-921b-efa394813fed	\N	{}	\N	f	f
da9a9697-7337-4b07-9005-b2e68b1dcf62	documents	attachments/organization_renewal/16/5dIPdVB0j21DTYm0kRGyGeetaBOLVjcXz3GZ0v5M.pdf	\N	2026-08-18 08:55:03.181293+00	2026-08-18 08:55:03.181293+00	2026-08-18 08:55:03.181293+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:03.179Z", "contentLength": 0, "httpStatusCode": 200}	a3e902bb-17bc-41d5-a289-028ddc11ee10	\N	{}	\N	f	f
ec121e2b-752d-4080-99f4-5c016899ec23	documents	attachments/organization_registration/36/JFgz3OfaWgahNoVql9P2VpDWYhyEOQeWbeivlEG8.pdf	\N	2026-08-21 06:20:17.082369+00	2026-08-21 06:20:17.082369+00	2026-08-21 06:20:17.082369+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:17.077Z", "contentLength": 0, "httpStatusCode": 200}	9225bed9-dd2c-462d-95f2-721ad26bc36a	\N	{}	\N	f	f
373a05fa-d443-403b-8821-a0ed06341d83	documents	attachments/organization_renewal/16/hC5zycVk801WsX1A4oHkVuSIGaO23eK7u6G4v1x0.pdf	\N	2026-08-18 08:55:04.079948+00	2026-08-18 08:55:04.079948+00	2026-08-18 08:55:04.079948+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:04.077Z", "contentLength": 0, "httpStatusCode": 200}	ea276acf-8bc3-49a0-90f8-d4f4a08bcf99	\N	{}	\N	f	f
71e2d139-3996-4786-90ff-7b92dc638877	documents	attachments/organization_renewal/49/sKVgTT07dqQYLPMDtoxi2VpL0RHSgld8f7RvEPx6.pdf	\N	2026-08-21 06:22:48.65013+00	2026-08-21 06:22:48.65013+00	2026-08-21 06:22:48.65013+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:48.645Z", "contentLength": 0, "httpStatusCode": 200}	1aca9e8e-b0c4-440d-b7b3-4d0619edac99	\N	{}	\N	f	f
70f2d6c6-6401-4245-a310-3051713d3a95	documents	attachments/organization_renewal/16/Tu7y1KnxJuch14F9pMmlQFFP9BK52iKsZdzb8VQJ.pdf	\N	2026-08-18 08:55:05.054294+00	2026-08-18 08:55:05.054294+00	2026-08-18 08:55:05.054294+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:05.052Z", "contentLength": 0, "httpStatusCode": 200}	0a4b993b-63aa-4b6c-aedf-3df4bebfdbb3	\N	{}	\N	f	f
3edd6148-f7b5-424e-88a6-c9ac098460c7	documents	attachments/organization_registration/37/Iov2s35LzV21q2BgUAGEpxqChNoQlR8Qc1PKmExL.pdf	\N	2026-08-21 06:20:19.428061+00	2026-08-21 06:20:19.428061+00	2026-08-21 06:20:19.428061+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:19.423Z", "contentLength": 0, "httpStatusCode": 200}	a212d028-db01-413e-b41d-2acae0f21290	\N	{}	\N	f	f
6a4c493f-47dc-4e9d-a3a4-3f9e044d647a	documents	attachments/organization_renewal/16/FKGiAYHgbIWUe1ZrUYvofa88FeU5PPz6ywMc47Z6.pdf	\N	2026-08-18 08:55:06.101971+00	2026-08-18 08:55:06.101971+00	2026-08-18 08:55:06.101971+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:06.100Z", "contentLength": 0, "httpStatusCode": 200}	4b057596-afd8-411a-84d0-abd9997ea6c1	\N	{}	\N	f	f
5392def9-508f-43a1-99e4-5b09b4f46a59	documents	attachments/organization_renewal/16/ZSmwRJ64fgh8feTdcT3srB1naF6zHoUsrjln7dHn.pdf	\N	2026-08-18 08:55:06.949357+00	2026-08-18 08:55:06.949357+00	2026-08-18 08:55:06.949357+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:06.947Z", "contentLength": 0, "httpStatusCode": 200}	429b6990-e083-4d70-8f9e-f6ec20157bfb	\N	{}	\N	f	f
97c6f117-b6f2-4000-a67c-5100a1bdf65b	documents	attachments/organization_registration/37/SMNR4Z3B8DxkY7nnQPnmYjwzxletLljdFYEJs7zR.pdf	\N	2026-08-21 06:20:22.043073+00	2026-08-21 06:20:22.043073+00	2026-08-21 06:20:22.043073+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:22.038Z", "contentLength": 0, "httpStatusCode": 200}	077e3321-c1ca-435d-bb8d-064b125191f7	\N	{}	\N	f	f
14decb01-4d6c-4ca3-9c08-ce1b5e74f48e	documents	attachments/organization_renewal/16/zmU4X1UqP5lw1E5vPr6gFxfv98f5IccHIpNPpE05.pdf	\N	2026-08-18 08:55:07.799877+00	2026-08-18 08:55:07.799877+00	2026-08-18 08:55:07.799877+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:07.797Z", "contentLength": 0, "httpStatusCode": 200}	1aac0b61-f69f-4190-b445-9cec76f3f5cf	\N	{}	\N	f	f
41218e82-6ac9-4d78-b547-12d86b8bbda8	documents	attachments/organization_renewal/16/Z4Ybpy9BtaxYC4gokbbSKfKTIpExTWtkHelhzA86.pdf	\N	2026-08-18 08:55:08.988021+00	2026-08-18 08:55:08.988021+00	2026-08-18 08:55:08.988021+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:08.986Z", "contentLength": 0, "httpStatusCode": 200}	cf936f18-966d-4435-88d4-37fc47dcfa8d	\N	{}	\N	f	f
0bb3692a-e5ad-447a-adb7-c3bc1dfb35f0	documents	attachments/organization_renewal/16/FWwW5g5KP5KhTC4vrRiTug5B6TSIJGweWdqe1T4L.pdf	\N	2026-08-18 08:55:09.856032+00	2026-08-18 08:55:09.856032+00	2026-08-18 08:55:09.856032+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:09.854Z", "contentLength": 0, "httpStatusCode": 200}	772a6441-e501-4d8e-8182-5d82958f4690	\N	{}	\N	f	f
faea943a-4b3c-43d4-830d-c2a0a2a56230	documents	attachments/organization_renewal/17/zkey1ldpenY49Bc7p7Zq4wac1F51OCzJTYJYRlcA.pdf	\N	2026-08-18 08:55:10.70436+00	2026-08-18 08:55:10.70436+00	2026-08-18 08:55:10.70436+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:10.702Z", "contentLength": 0, "httpStatusCode": 200}	85ac05f9-be35-460a-8887-b43f6ebf4e0c	\N	{}	\N	f	f
4bcb096a-5577-44cb-8601-12567da85581	documents	attachments/organization_registration/37/w84qboGH2iKLadW32T0lvc9uiZJZFDybTqAIlA6L.pdf	\N	2026-08-21 06:20:24.261895+00	2026-08-21 06:20:24.261895+00	2026-08-21 06:20:24.261895+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:24.256Z", "contentLength": 0, "httpStatusCode": 200}	60fd7d12-379f-47e8-a547-e45a1855b7c6	\N	{}	\N	f	f
0d69c7f7-e379-45ce-87f9-0e2a72447c3f	documents	attachments/organization_renewal/17/oAKPeERm0MxT4dOgkC75BlmwygAtPXIfQBNjs34S.pdf	\N	2026-08-18 08:55:11.570849+00	2026-08-18 08:55:11.570849+00	2026-08-18 08:55:11.570849+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-18T08:55:11.568Z", "contentLength": 0, "httpStatusCode": 200}	5de2fc14-6cd9-411d-8131-53438ea337e5	\N	{}	\N	f	f
b0543c99-8732-4b70-94da-111ead7fd210	documents	attachments/organization_renewal/49/5bZs2iSMglhMtdUYIghWbEZUg0CarnTDLynVrkJb.pdf	\N	2026-08-21 06:22:50.895179+00	2026-08-21 06:22:50.895179+00	2026-08-21 06:22:50.895179+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:50.890Z", "contentLength": 0, "httpStatusCode": 200}	1e3e0fae-cdcd-4b0f-9b3e-0ce0e2442c82	\N	{}	\N	f	f
7d715452-e104-4878-8750-05d128fc434e	documents	attachments/organization_registration/37/zJagEgzhgApz6qA2aZe9Kq3lGaZeNeRblopzTJUL.pdf	\N	2026-08-21 06:20:26.716571+00	2026-08-21 06:20:26.716571+00	2026-08-21 06:20:26.716571+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:26.711Z", "contentLength": 0, "httpStatusCode": 200}	8c29427e-6bb5-4f9f-bc07-5716672e4a0e	\N	{}	\N	f	f
eba52094-31e8-47cd-8bfe-85c3adf45e34	documents	attachments/organization_registration/37/XwvFcdHwOnDHZiXGQgUlwRnfIplmozT2oLF2TpX9.pdf	\N	2026-08-21 06:20:29.138259+00	2026-08-21 06:20:29.138259+00	2026-08-21 06:20:29.138259+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:29.133Z", "contentLength": 0, "httpStatusCode": 200}	985feb3f-3b79-45ee-b1c8-9512d218347a	\N	{}	\N	f	f
c984913b-cb35-4bb2-943d-d17cc229e799	documents	attachments/organization_registration/37/YnGLZthomq7cjBgIEBi2fm9GsG1OFZxrZOQMLts1.pdf	\N	2026-08-21 06:20:31.427031+00	2026-08-21 06:20:31.427031+00	2026-08-21 06:20:31.427031+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:31.422Z", "contentLength": 0, "httpStatusCode": 200}	296e9bbf-ac7c-4afb-8b55-9a6141618647	\N	{}	\N	f	f
9872b9ea-6c6d-4268-b5e9-da8512d413fa	documents	attachments/organization_registration/38/aAnn3ko6UlIe5roJGyNDWO8KepcKLZk4F6jUWM2q.pdf	\N	2026-08-21 06:20:33.651716+00	2026-08-21 06:20:33.651716+00	2026-08-21 06:20:33.651716+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:33.647Z", "contentLength": 0, "httpStatusCode": 200}	8d11cbb7-abcc-41b1-baaa-b5078bc6ecf9	\N	{}	\N	f	f
8f987eba-408e-4b3e-b4d2-70f3be82252c	documents	attachments/organization_renewal/49/flKnibLKdXrFG4VvD5edizHvuBDJJ5HokwFYC50l.pdf	\N	2026-08-21 06:22:55.364994+00	2026-08-21 06:22:55.364994+00	2026-08-21 06:22:55.364994+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:55.360Z", "contentLength": 0, "httpStatusCode": 200}	2f0d937b-1270-4799-b21a-2b2991653fd7	\N	{}	\N	f	f
02a9140c-1653-499a-be19-c1a654d81c1a	documents	attachments/organization_registration/38/7A6H4Nmtv05WDFq5XXHa9qesJXPCN0STkWbsLlWy.pdf	\N	2026-08-21 06:20:35.868581+00	2026-08-21 06:20:35.868581+00	2026-08-21 06:20:35.868581+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:35.863Z", "contentLength": 0, "httpStatusCode": 200}	ef501a35-8ae7-4e02-9267-8a7cc6f08496	\N	{}	\N	f	f
0c9a2d89-55ae-42c5-99b1-a352d88c2c06	documents	attachments/organization_registration/38/DddGRCYY0ayYFDuxIIFdWJWxapcP72AN4J6beIHj.pdf	\N	2026-08-21 06:20:38.076409+00	2026-08-21 06:20:38.076409+00	2026-08-21 06:20:38.076409+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:38.071Z", "contentLength": 0, "httpStatusCode": 200}	6878da9f-e867-4d93-91d4-38152cfd8a55	\N	{}	\N	f	f
cd59da2f-0eff-4be0-8329-4f7c6ccb70e3	documents	attachments/organization_registration/38/WjYx4mqVSjv14GV9hMTJJcqzejkvt844tL6fvys0.pdf	\N	2026-08-21 06:20:40.283382+00	2026-08-21 06:20:40.283382+00	2026-08-21 06:20:40.283382+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:40.278Z", "contentLength": 0, "httpStatusCode": 200}	41994b4c-86e2-45e2-b05a-74f2d303ec88	\N	{}	\N	f	f
3851c72a-7c5e-4a66-ac6b-6b60bddc0b8c	documents	attachments/organization_renewal/49/zVBFhSQuwj9UCQ2EmHXBvHXGdIzvmJcbUcSgsU2J.pdf	\N	2026-08-21 06:22:57.652038+00	2026-08-21 06:22:57.652038+00	2026-08-21 06:22:57.652038+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:57.647Z", "contentLength": 0, "httpStatusCode": 200}	4d67bb7f-db89-4d97-92c4-e27956d74564	\N	{}	\N	f	f
82693469-66b7-4991-90ee-78ef80fe8bf0	documents	attachments/organization_registration/39/ltgplTW90yKPNpccEac5PxzG5on00qahw25sKy34.pdf	\N	2026-08-21 06:20:47.106095+00	2026-08-21 06:20:47.106095+00	2026-08-21 06:20:47.106095+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:47.101Z", "contentLength": 0, "httpStatusCode": 200}	4ad87fa9-8d0e-4d42-8c44-924c1ab16237	\N	{}	\N	f	f
c8483160-162f-4948-be5b-4306b0b70560	documents	attachments/organization_registration/39/tmd9BBZCcuJh9j77gwqaeMSvkb91gnu6WirBZoL7.pdf	\N	2026-08-21 06:20:49.328312+00	2026-08-21 06:20:49.328312+00	2026-08-21 06:20:49.328312+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:49.323Z", "contentLength": 0, "httpStatusCode": 200}	f1dc18de-160a-4f53-9ef7-de9cd64a7714	\N	{}	\N	f	f
334a8471-ede5-4ebe-b452-e6191566d291	documents	attachments/organization_renewal/49/pZOpvjvZQ0rwq9Dt2j12BrT6cxqRPVsedzfjR9oU.pdf	\N	2026-08-21 06:22:59.894245+00	2026-08-21 06:22:59.894245+00	2026-08-21 06:22:59.894245+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:59.889Z", "contentLength": 0, "httpStatusCode": 200}	dd849f49-37d8-4f53-ba6e-21f287b86fd2	\N	{}	\N	f	f
e006f1fe-05ab-47b6-b065-7e10a4ac10eb	documents	attachments/organization_registration/39/ZFVUdy7giNqt9Iefv889JoZ3BQZIZHFzs1FdTtvQ.pdf	\N	2026-08-21 06:20:51.537501+00	2026-08-21 06:20:51.537501+00	2026-08-21 06:20:51.537501+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:51.532Z", "contentLength": 0, "httpStatusCode": 200}	6d3fd409-09ae-4939-82fd-6f182838673e	\N	{}	\N	f	f
b2e9ddfe-ba24-4844-90f2-e83f4475c740	documents	attachments/organization_registration/39/CueoK2qPrOi3HsXtjlpBMMzPPwUH5TizILjti1Za.pdf	\N	2026-08-21 06:20:53.75644+00	2026-08-21 06:20:53.75644+00	2026-08-21 06:20:53.75644+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:53.751Z", "contentLength": 0, "httpStatusCode": 200}	c16289f3-929a-46a7-8b89-8bfdf91a31a6	\N	{}	\N	f	f
239032bd-bfcb-4746-8c0f-30a6657330c5	documents	attachments/organization_renewal/49/cD2N9UOd2BYyQVGRcLrbm9831ttaeGoUcL8B5Pmt.pdf	\N	2026-08-21 06:23:02.118676+00	2026-08-21 06:23:02.118676+00	2026-08-21 06:23:02.118676+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:02.113Z", "contentLength": 0, "httpStatusCode": 200}	7451069c-8ff4-4cc9-82dc-72d4ce0f932a	\N	{}	\N	f	f
fbb06981-1507-48b8-978f-133debb94782	documents	attachments/organization_registration/39/c3BmAhAqVFoLILwUkrV7GhfrtUzo0MCWtJKnCukE.pdf	\N	2026-08-21 06:20:56.128047+00	2026-08-21 06:20:56.128047+00	2026-08-21 06:20:56.128047+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:56.123Z", "contentLength": 0, "httpStatusCode": 200}	29559f5f-ca91-4cea-9060-6a60a97769fc	\N	{}	\N	f	f
c628094e-863e-481a-8252-08f5d6655383	documents	attachments/organization_registration/39/IHKlqBRcqNkNlD22INkSQldSjsF7FHaeKlm3KSR1.pdf	\N	2026-08-21 06:20:58.433623+00	2026-08-21 06:20:58.433623+00	2026-08-21 06:20:58.433623+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:20:58.429Z", "contentLength": 0, "httpStatusCode": 200}	0b99010d-4c5a-4c45-8c34-2908fa27916b	\N	{}	\N	f	f
927db8c8-1cf9-428a-87e5-6ab9d17ee9e6	documents	attachments/organization_renewal/50/3SrncXc54UqUX72usnunpWQtiuoZRvnzcBhEbQVL.pdf	\N	2026-08-21 06:23:04.366411+00	2026-08-21 06:23:04.366411+00	2026-08-21 06:23:04.366411+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:04.361Z", "contentLength": 0, "httpStatusCode": 200}	b100be73-a50d-4da6-aa60-964910e7c902	\N	{}	\N	f	f
64a8abd8-a181-421c-a754-044c0f851ecc	documents	attachments/organization_registration/40/IEjkZ1IkElgfWJPutNbG3Ll1Bh7P4gXHttNaabzt.pdf	\N	2026-08-21 06:21:00.687502+00	2026-08-21 06:21:00.687502+00	2026-08-21 06:21:00.687502+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:00.682Z", "contentLength": 0, "httpStatusCode": 200}	9825f186-5af7-4c31-b546-1805e9539bb7	\N	{}	\N	f	f
ede3d773-23e0-41a8-8e93-3d30186166dc	documents	attachments/organization_registration/40/PnosPmQ5Yan5wPuh82VgQ1ixsMUeJ72IfpiMaWCk.pdf	\N	2026-08-21 06:21:02.890002+00	2026-08-21 06:21:02.890002+00	2026-08-21 06:21:02.890002+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:02.885Z", "contentLength": 0, "httpStatusCode": 200}	636b69bb-3691-4de5-a8fc-2dff5677db00	\N	{}	\N	f	f
42b1e401-cbb7-4400-a018-ddb3366d86a4	documents	attachments/organization_registration/40/5Gf54raX250twp0w1yBRvNchFNLMzb7IMPxYaAQx.pdf	\N	2026-08-21 06:21:05.123304+00	2026-08-21 06:21:05.123304+00	2026-08-21 06:21:05.123304+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:05.118Z", "contentLength": 0, "httpStatusCode": 200}	cfda0b3e-4bb0-4240-a1f2-c18f6e4dc0fc	\N	{}	\N	f	f
4e4bf804-dff0-4770-b5b9-f317ceb22252	documents	attachments/organization_registration/40/DfPiqPkOwQtRzOjz4xqoezCowePv9vDsF1yHIpOu.pdf	\N	2026-08-21 06:21:07.376922+00	2026-08-21 06:21:07.376922+00	2026-08-21 06:21:07.376922+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:07.372Z", "contentLength": 0, "httpStatusCode": 200}	f829aa88-8064-42f0-8b4b-3e02989febec	\N	{}	\N	f	f
cb829c18-3a31-4529-a2ae-46be4de3fb1b	documents	attachments/organization_renewal/50/c3dLPvcDszDNzu9d56LQxFN4nmztARF25An4VsJE.pdf	\N	2026-08-21 06:23:06.595769+00	2026-08-21 06:23:06.595769+00	2026-08-21 06:23:06.595769+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:06.590Z", "contentLength": 0, "httpStatusCode": 200}	fcb8b345-1458-4c67-b19d-f723e9a8665f	\N	{}	\N	f	f
5d36b3d4-7cdf-45e0-b0e6-ecc01e4ff115	documents	attachments/organization_registration/40/K8TVIrpAajUvnnESF8nNlBaXyl8hFkZXkAHt3Lf3.pdf	\N	2026-08-21 06:21:09.588908+00	2026-08-21 06:21:09.588908+00	2026-08-21 06:21:09.588908+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:09.584Z", "contentLength": 0, "httpStatusCode": 200}	e13255a0-2d22-4644-afa6-3a7339d5f935	\N	{}	\N	f	f
1791f53f-eef1-4186-92a2-0d8eaf07788b	documents	attachments/organization_registration/40/sDTDna2bGbtyvVTYGhctRwJbeJt7HT6INofejIa8.pdf	\N	2026-08-21 06:21:12.381064+00	2026-08-21 06:21:12.381064+00	2026-08-21 06:21:12.381064+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:12.376Z", "contentLength": 0, "httpStatusCode": 200}	2ab3a103-afae-445d-b407-e257bb9d3410	\N	{}	\N	f	f
1085d41e-7a09-4b1a-b6eb-eec7b6c3cb5d	documents	attachments/organization_renewal/50/yOvicAy1HyNObo9MMc8hmk6rZF4Uwy8xV4MBxRvN.pdf	\N	2026-08-21 06:23:08.830725+00	2026-08-21 06:23:08.830725+00	2026-08-21 06:23:08.830725+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:08.825Z", "contentLength": 0, "httpStatusCode": 200}	9fb634c6-bf8f-435c-a48b-c25366278019	\N	{}	\N	f	f
798c01a6-a885-4d1a-8509-9b19d9ff2c11	documents	attachments/organization_registration/41/jB1241m1OiwRMuDkilGmCVB96rdL9YFSCPTmDkj2.pdf	\N	2026-08-21 06:21:14.744039+00	2026-08-21 06:21:14.744039+00	2026-08-21 06:21:14.744039+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:14.739Z", "contentLength": 0, "httpStatusCode": 200}	f61038b6-ffe7-4159-8d92-b57b87c39957	\N	{}	\N	f	f
2c99818f-00b5-4221-9efc-d611e54be241	documents	attachments/organization_registration/41/QH6302pwahIuot9fpV0wIDwd2li5b3XRhoUXjUxm.pdf	\N	2026-08-21 06:21:17.015683+00	2026-08-21 06:21:17.015683+00	2026-08-21 06:21:17.015683+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:17.010Z", "contentLength": 0, "httpStatusCode": 200}	5b1ab43e-6cda-46fc-b823-1dcd7fcfcb37	\N	{}	\N	f	f
e6fc838b-246c-432a-b17f-3f807c41aeb3	documents	attachments/organization_renewal/50/I1ul01RU6syXCmjhypwBgoM3EOhHb9AzLSForiLC.pdf	\N	2026-08-21 06:23:11.051659+00	2026-08-21 06:23:11.051659+00	2026-08-21 06:23:11.051659+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:11.047Z", "contentLength": 0, "httpStatusCode": 200}	b8cc64bb-4882-42d9-a613-f6a633c589c3	\N	{}	\N	f	f
2281bd10-dd86-45bf-91ed-270c404c5b46	documents	attachments/organization_registration/41/UHpRqxy38wnmUWXchlcIWTuSVP3gktBaUPo3xU59.pdf	\N	2026-08-21 06:21:19.232906+00	2026-08-21 06:21:19.232906+00	2026-08-21 06:21:19.232906+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:19.228Z", "contentLength": 0, "httpStatusCode": 200}	11a2539f-b8e9-4995-8b70-e5953e1565e5	\N	{}	\N	f	f
0b541fa6-a293-4423-8750-4536e466d2ce	documents	attachments/organization_registration/41/oPvKJDOvWOTGaoKya1ZJKWjny4FttvzHGRwxSOFa.pdf	\N	2026-08-21 06:21:21.492107+00	2026-08-21 06:21:21.492107+00	2026-08-21 06:21:21.492107+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:21.487Z", "contentLength": 0, "httpStatusCode": 200}	3451e50e-7c9f-4582-a5e7-37e318490a96	\N	{}	\N	f	f
3f3d9c8b-fab9-4593-ad3e-1aa51e02a456	documents	attachments/organization_registration/41/gnhpfeZmW9dIpCbKGIEyCuYtIkAKyrNUfKbZsvmm.pdf	\N	2026-08-21 06:21:23.921614+00	2026-08-21 06:21:23.921614+00	2026-08-21 06:21:23.921614+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:23.916Z", "contentLength": 0, "httpStatusCode": 200}	839502ab-172d-42bc-93ba-81455e5fcd2b	\N	{}	\N	f	f
ac93af8a-6a52-40fb-a4f8-fc95c3cc54a2	documents	attachments/organization_renewal/50/RRQu4TUfd9BzqmHtlFjkUcgWdyCFjhObAdmvEI5j.pdf	\N	2026-08-21 06:23:13.253598+00	2026-08-21 06:23:13.253598+00	2026-08-21 06:23:13.253598+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:13.248Z", "contentLength": 0, "httpStatusCode": 200}	f5d4202f-324e-481c-902e-af06a9b2a582	\N	{}	\N	f	f
511c3b40-617f-46aa-9ef9-8c69a8d12503	documents	attachments/organization_registration/41/v9Q9dOABSJPjd0qTM9PP2u9SkW8e2wugGP1ubUK7.pdf	\N	2026-08-21 06:21:26.219905+00	2026-08-21 06:21:26.219905+00	2026-08-21 06:21:26.219905+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:26.214Z", "contentLength": 0, "httpStatusCode": 200}	a7b74694-01ee-418e-9822-1d9515861f38	\N	{}	\N	f	f
527eb798-ca96-4c83-a10f-4c4c16032ca6	documents	attachments/organization_registration/42/ej4AdTmHquDPpAvtZcRBnICbs2nRcxZgfnnGQhmd.pdf	\N	2026-08-21 06:21:28.424201+00	2026-08-21 06:21:28.424201+00	2026-08-21 06:21:28.424201+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:28.419Z", "contentLength": 0, "httpStatusCode": 200}	52370637-9023-4fed-aec2-db568d416bf5	\N	{}	\N	f	f
a35922e0-0369-44bd-b875-026599a8641f	documents	attachments/organization_renewal/50/KINF6BvAIWeqqMYMNXfviRHwHQVLNhZU67cYUXGv.pdf	\N	2026-08-21 06:23:15.472945+00	2026-08-21 06:23:15.472945+00	2026-08-21 06:23:15.472945+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:15.468Z", "contentLength": 0, "httpStatusCode": 200}	39e171f7-c1fc-4de6-b7fb-ae3a5c296d91	\N	{}	\N	f	f
30cee25e-f89e-4485-9384-aea0ceaf2eb8	documents	attachments/organization_registration/42/5kfImGN5Pp2bYRdk4siEOduPJTM7F7ubCsKBytSR.pdf	\N	2026-08-21 06:21:30.697607+00	2026-08-21 06:21:30.697607+00	2026-08-21 06:21:30.697607+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:30.692Z", "contentLength": 0, "httpStatusCode": 200}	14e4cd5d-6106-4a4d-8d51-89fd84ca0c99	\N	{}	\N	f	f
38901c80-147e-4901-ba36-7528807bca67	documents	attachments/organization_registration/42/bOIxI5XTzdG5NI1uHXMgBQvndZD6MdRuPQAsXpEx.pdf	\N	2026-08-21 06:21:32.929016+00	2026-08-21 06:21:32.929016+00	2026-08-21 06:21:32.929016+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:32.923Z", "contentLength": 0, "httpStatusCode": 200}	fd97b557-dec0-4472-9867-80fa383281cf	\N	{}	\N	f	f
564e2aa5-53b2-4905-a747-cc3b35e6470f	documents	attachments/organization_renewal/50/HjvTII6YjLi9aql8d2dZrJzSlYReiUIwE6oGgQNs.pdf	\N	2026-08-21 06:23:17.681897+00	2026-08-21 06:23:17.681897+00	2026-08-21 06:23:17.681897+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:17.676Z", "contentLength": 0, "httpStatusCode": 200}	ca574429-a5d7-4e71-8846-65c649555858	\N	{}	\N	f	f
fae53c18-c521-43e8-8295-201d3c003c04	documents	attachments/organization_registration/42/gZnGQ9qnCAblVLBFXH6OEegYD8zTKVktu5bKnQ7c.pdf	\N	2026-08-21 06:21:35.134583+00	2026-08-21 06:21:35.134583+00	2026-08-21 06:21:35.134583+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:35.129Z", "contentLength": 0, "httpStatusCode": 200}	cb8d8b3e-b5d0-4852-8c26-d9637604d391	\N	{}	\N	f	f
24058771-2808-483b-b6de-52aaf3298849	documents	attachments/organization_registration/42/YAUD0JCpemtV0qkmVgvpESZw5LzT70wiyW6Ja7Jw.pdf	\N	2026-08-21 06:21:37.348353+00	2026-08-21 06:21:37.348353+00	2026-08-21 06:21:37.348353+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:37.343Z", "contentLength": 0, "httpStatusCode": 200}	94ed41c3-428f-4a0d-9322-eff915216edf	\N	{}	\N	f	f
2e2d089e-2982-485e-980e-a1d019edff16	documents	attachments/organization_renewal/50/0WuWXTPnNoOftsGfQZYlG4TehYhCdN1pOQ9PLvVy.pdf	\N	2026-08-21 06:23:19.896319+00	2026-08-21 06:23:19.896319+00	2026-08-21 06:23:19.896319+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:19.891Z", "contentLength": 0, "httpStatusCode": 200}	db9774e3-64bd-4d82-9bf8-94d78f243983	\N	{}	\N	f	f
273559ea-de13-42b3-a583-61651ef19a5c	documents	attachments/organization_registration/42/5okyEoZi5VOroo5FLlw6lNh52YtXUcAbWfnVOg8U.pdf	\N	2026-08-21 06:21:39.667014+00	2026-08-21 06:21:39.667014+00	2026-08-21 06:21:39.667014+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:39.662Z", "contentLength": 0, "httpStatusCode": 200}	9d6a6912-665e-4a99-8e86-4584c79f8b63	\N	{}	\N	f	f
ae2c62d8-e052-4902-b959-a30995ca7732	documents	attachments/organization_registration/44/5oqRIJXMXhYs3kY1hXfYOSFNKKUXk6ZCQowwfeWH.pdf	\N	2026-08-21 06:21:41.908158+00	2026-08-21 06:21:41.908158+00	2026-08-21 06:21:41.908158+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:41.903Z", "contentLength": 0, "httpStatusCode": 200}	f4fe2dda-3b23-4688-ad6b-3631330e3314	\N	{}	\N	f	f
0ec50a08-6ca6-4ecc-a9e9-fd9cc208cd02	documents	attachments/organization_registration/44/Fg85fN6pquyHXjBNo5PmzM68AJ3zAYUtxOywJhIO.pdf	\N	2026-08-21 06:21:44.159331+00	2026-08-21 06:21:44.159331+00	2026-08-21 06:21:44.159331+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:44.154Z", "contentLength": 0, "httpStatusCode": 200}	8ec863c3-8944-4342-ad7d-6e883479cd61	\N	{}	\N	f	f
11a1de47-341e-46b4-9919-86bfeff1c288	documents	attachments/organization_renewal/50/8aYHCgfSuowoJZzA2pDt0O8zVfEhBn4WdAmPBKmm.pdf	\N	2026-08-21 06:23:22.136386+00	2026-08-21 06:23:22.136386+00	2026-08-21 06:23:22.136386+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:22.131Z", "contentLength": 0, "httpStatusCode": 200}	bfc59a44-7e64-4775-8b92-7139f1c7e432	\N	{}	\N	f	f
a1c0adb2-f844-4a5e-9373-521dc33c5f4e	documents	attachments/organization_registration/44/amTtCqiP8W75uKjQlGWbswLw2CmRNsbzniif6EK9.pdf	\N	2026-08-21 06:21:46.396159+00	2026-08-21 06:21:46.396159+00	2026-08-21 06:21:46.396159+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:46.391Z", "contentLength": 0, "httpStatusCode": 200}	4974dad9-0fbd-4f22-bd6a-df8641e3328b	\N	{}	\N	f	f
e8f41450-370d-40f9-a178-f2b55ad6b20a	documents	attachments/organization_registration/44/DCoshgngMccy5RrY0yhNYiYDP5yTaI2JA6yPWtBx.pdf	\N	2026-08-21 06:21:48.610904+00	2026-08-21 06:21:48.610904+00	2026-08-21 06:21:48.610904+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:48.605Z", "contentLength": 0, "httpStatusCode": 200}	e1ff7bc8-b895-4ba5-b499-c0f5dd7ceb4f	\N	{}	\N	f	f
af9f3a3b-f1ef-4667-bc04-c0c419adf361	documents	attachments/organization_renewal/51/EfogMIpeVIEmi571vZ7zUaLQIZNn8gjjhJWTbcdR.pdf	\N	2026-08-21 06:23:24.375292+00	2026-08-21 06:23:24.375292+00	2026-08-21 06:23:24.375292+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:24.366Z", "contentLength": 0, "httpStatusCode": 200}	14f1fe1d-e543-46d8-8af7-d0b494afd158	\N	{}	\N	f	f
4040b1f7-d0f9-4cda-bac2-79116bcc9528	documents	attachments/organization_registration/44/p5sYP5uhe0Cz8e64CbIS8bbbdpT2XpP58fRKjFbS.pdf	\N	2026-08-21 06:21:51.048144+00	2026-08-21 06:21:51.048144+00	2026-08-21 06:21:51.048144+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:51.043Z", "contentLength": 0, "httpStatusCode": 200}	2b57a379-28f8-4b81-b027-773d488cedd0	\N	{}	\N	f	f
fbcb2471-3177-40f1-8cef-0068febbe40d	documents	attachments/organization_registration/44/zAltZjD4iBr4fTouoVjGkEQWeG7hxUNtcPEHC59Y.pdf	\N	2026-08-21 06:21:53.321507+00	2026-08-21 06:21:53.321507+00	2026-08-21 06:21:53.321507+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:53.316Z", "contentLength": 0, "httpStatusCode": 200}	e532abeb-8893-4463-9ab6-f9c7571417e9	\N	{}	\N	f	f
fc4fa2a0-935a-4b00-a775-7d77334e5d3d	documents	attachments/organization_renewal/51/mBOT4KwpCEExjmmPV1WyLIzNHiWdXR6puKOSMlXy.pdf	\N	2026-08-21 06:23:26.572297+00	2026-08-21 06:23:26.572297+00	2026-08-21 06:23:26.572297+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:26.567Z", "contentLength": 0, "httpStatusCode": 200}	13609591-1f12-4eab-9fd2-1dc654948978	\N	{}	\N	f	f
130774ca-3492-44b2-9dba-efc57075783f	documents	attachments/organization_registration/45/E1i48Fl928cRLsTmOgXZ0S2BiknhWosSiKBNb3SB.pdf	\N	2026-08-21 06:21:55.541717+00	2026-08-21 06:21:55.541717+00	2026-08-21 06:21:55.541717+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:55.536Z", "contentLength": 0, "httpStatusCode": 200}	a37abe7a-a96e-4906-ab7d-1c5a8796a7c8	\N	{}	\N	f	f
a8d91528-472f-445a-a36c-84a8c3792d14	documents	attachments/organization_registration/45/3hjNRVxewo11WbbJLVYVzyrZSxzQ5fEAgpZC960Z.pdf	\N	2026-08-21 06:21:57.744809+00	2026-08-21 06:21:57.744809+00	2026-08-21 06:21:57.744809+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:21:57.739Z", "contentLength": 0, "httpStatusCode": 200}	e84b29c4-4b88-44b3-9d7a-de38b55c4815	\N	{}	\N	f	f
5d928aa3-28ce-4a51-a1ec-f24d9b210144	documents	attachments/organization_registration/45/FhsVfpKw5QIePCGLXSnLPOvLYACuyQ4e8PGAOgFX.pdf	\N	2026-08-21 06:22:00.019113+00	2026-08-21 06:22:00.019113+00	2026-08-21 06:22:00.019113+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:00.014Z", "contentLength": 0, "httpStatusCode": 200}	052097d7-69d2-47d1-a329-77b40f4e3c2f	\N	{}	\N	f	f
2b1a38f4-67e8-4e16-ad8f-4ae99c2991ba	documents	attachments/organization_renewal/51/6eFACHPyoE5w1Hc1YjF2SsGggefJZ3Xd5AGBRdrs.pdf	\N	2026-08-21 06:23:28.856171+00	2026-08-21 06:23:28.856171+00	2026-08-21 06:23:28.856171+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:28.851Z", "contentLength": 0, "httpStatusCode": 200}	b2bb0da3-cbea-4799-8e1d-3f9f5eb8754a	\N	{}	\N	f	f
0e59271c-b388-4a87-813c-4166328d240f	documents	attachments/organization_registration/45/g2RdhgO2gW8ChuIXDymUNgEIDOVqwMU7PY8bfO2H.pdf	\N	2026-08-21 06:22:02.25059+00	2026-08-21 06:22:02.25059+00	2026-08-21 06:22:02.25059+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:02.245Z", "contentLength": 0, "httpStatusCode": 200}	d0bb721f-4350-42d2-9da9-b14499f94a62	\N	{}	\N	f	f
e1b51565-dacf-4792-8217-01d655c93c81	documents	attachments/organization_registration/45/vFRpq3cxWjEsLdufUcDC4Ydb6piTtANa0vjFMehr.pdf	\N	2026-08-21 06:22:04.500336+00	2026-08-21 06:22:04.500336+00	2026-08-21 06:22:04.500336+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:04.495Z", "contentLength": 0, "httpStatusCode": 200}	0292278d-5e3e-4120-a2a1-ed9272ffd6e5	\N	{}	\N	f	f
3725babc-5dde-41ae-9315-a621f013b539	documents	attachments/organization_renewal/51/oN91CixtSS2v3xSuxW7A3HIEeeIRDdKmoR7lHy2r.pdf	\N	2026-08-21 06:23:31.405619+00	2026-08-21 06:23:31.405619+00	2026-08-21 06:23:31.405619+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:31.400Z", "contentLength": 0, "httpStatusCode": 200}	601f71ab-eb43-4713-aa1d-421c1379ccbf	\N	{}	\N	f	f
1300a475-6503-4204-a74e-c11bd21817b6	documents	attachments/organization_registration/45/9y5vzc9ZL7lQHazkcQwXIEPJDLpJ5c9hREtoS7vQ.pdf	\N	2026-08-21 06:22:06.728664+00	2026-08-21 06:22:06.728664+00	2026-08-21 06:22:06.728664+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:06.723Z", "contentLength": 0, "httpStatusCode": 200}	e538c9d5-34fe-4bf2-80ec-7a298e881ffc	\N	{}	\N	f	f
500485c2-1142-4802-acaa-39c7c5c1fdfb	documents	attachments/organization_registration/46/LNkMubvkaqoF814vuh3rSslcVfjDyea47EF2EggU.pdf	\N	2026-08-21 06:22:09.064784+00	2026-08-21 06:22:09.064784+00	2026-08-21 06:22:09.064784+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:09.059Z", "contentLength": 0, "httpStatusCode": 200}	23689ff0-b909-47d0-ab65-d9a5dcf6f09f	\N	{}	\N	f	f
7a21f080-c2b0-4bc3-9fc0-db629b4734cc	documents	attachments/organization_renewal/51/dCHOan3XEiyeZRTfWzrewjkYAK0rFQOb5KrIdqmn.pdf	\N	2026-08-21 06:23:33.603277+00	2026-08-21 06:23:33.603277+00	2026-08-21 06:23:33.603277+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:33.598Z", "contentLength": 0, "httpStatusCode": 200}	d9709377-2fa0-4ad9-afd6-995fb1cb567d	\N	{}	\N	f	f
f0923f5c-a76e-4b38-a304-c0727d029f56	documents	attachments/organization_registration/46/UwqTsSNQe0kyzjFMsgVvAhXJ78AIpQFvBdaDpVD0.pdf	\N	2026-08-21 06:22:11.266472+00	2026-08-21 06:22:11.266472+00	2026-08-21 06:22:11.266472+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:11.261Z", "contentLength": 0, "httpStatusCode": 200}	beddf9ff-78fd-4a58-9664-68926d54789c	\N	{}	\N	f	f
ae4127f1-1dab-4961-a5e0-572e313393c1	documents	attachments/organization_registration/46/ZUAns9hqwQb49XlGxj9scFVADscsIqeeig5Xe4c9.pdf	\N	2026-08-21 06:22:14.166982+00	2026-08-21 06:22:14.166982+00	2026-08-21 06:22:14.166982+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:14.162Z", "contentLength": 0, "httpStatusCode": 200}	037f63c2-411a-4899-b054-f5c5136038e8	\N	{}	\N	f	f
0454e46a-4e9f-44e5-b7ee-30c155d24547	documents	attachments/organization_renewal/51/gWuMCRPsy4R069olEhGhXEAoJYZOLBTAZuh3QicP.pdf	\N	2026-08-21 06:23:35.82345+00	2026-08-21 06:23:35.82345+00	2026-08-21 06:23:35.82345+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:35.818Z", "contentLength": 0, "httpStatusCode": 200}	8b7c30f1-1808-41d9-a70d-e6b53af79258	\N	{}	\N	f	f
9355e928-7fb5-4962-b709-01c81326631e	documents	attachments/organization_registration/46/xf6Kp7MjaqvWCGGyOkUQTDEmhPqKV4nXLuQodB1z.pdf	\N	2026-08-21 06:22:16.407619+00	2026-08-21 06:22:16.407619+00	2026-08-21 06:22:16.407619+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:16.402Z", "contentLength": 0, "httpStatusCode": 200}	49f2dfe9-a113-4c3e-9554-02c0395a1c80	\N	{}	\N	f	f
b48bb93a-5d80-4262-ad6f-644be29c4676	documents	attachments/organization_registration/46/JwvdqnCyav05ezJ9a7CVVIziixI3i5PR6BOQz71j.pdf	\N	2026-08-21 06:22:18.647051+00	2026-08-21 06:22:18.647051+00	2026-08-21 06:22:18.647051+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:18.641Z", "contentLength": 0, "httpStatusCode": 200}	f2f8efd5-03c0-4bbe-bf89-3759a351d585	\N	{}	\N	f	f
418b0ea9-7926-45f9-8af5-40392f6bfd7a	documents	attachments/organization_registration/46/MkobjxBpOwrRSA7PY6szoKNrJuwg1UnBWm2B953g.pdf	\N	2026-08-21 06:22:20.928319+00	2026-08-21 06:22:20.928319+00	2026-08-21 06:22:20.928319+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:22:20.923Z", "contentLength": 0, "httpStatusCode": 200}	4a9fe63b-2aa4-4545-9b28-c3be2f62c8c6	\N	{}	\N	f	f
f1c522ae-58f2-4c5b-805e-0fc254142a05	documents	attachments/organization_renewal/51/XiUERPniItaHFKhRGGCvGr9GcRoLhSnvzeUfduOf.pdf	\N	2026-08-21 06:23:38.032608+00	2026-08-21 06:23:38.032608+00	2026-08-21 06:23:38.032608+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:38.027Z", "contentLength": 0, "httpStatusCode": 200}	a12517b5-c581-4b6c-baa3-ac49ec14ea7b	\N	{}	\N	f	f
8a3dc890-b4a4-4a88-95b3-4918e3e11c88	documents	attachments/organization_renewal/51/iNzIHLQypkfPdm1zmkkFxHPa7v427DRoo1f09y4N.pdf	\N	2026-08-21 06:23:41.56249+00	2026-08-21 06:23:41.56249+00	2026-08-21 06:23:41.56249+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:41.557Z", "contentLength": 0, "httpStatusCode": 200}	8ca364d3-8d95-418f-bc0b-4061ab6bb0c6	\N	{}	\N	f	f
9ac8d82b-e201-46c5-b609-ba0ead344aa4	documents	attachments/organization_renewal/51/qlvT6GE39aIIehHHUzHrFF1fCaMs9MRQ9RrfX44R.pdf	\N	2026-08-21 06:23:43.808297+00	2026-08-21 06:23:43.808297+00	2026-08-21 06:23:43.808297+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:43.803Z", "contentLength": 0, "httpStatusCode": 200}	3156704c-db90-45a2-abdb-b33f1dbbac8a	\N	{}	\N	f	f
8b165ab8-4da1-496d-8030-79561fb5e715	documents	attachments/after_activity_report/67/aQZuKHlp9GxZRetahffXKKpBHb7LNPedShhlGfCt.jpg	\N	2026-08-21 06:23:46.077746+00	2026-08-21 06:23:46.077746+00	2026-08-21 06:23:46.077746+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "image/jpeg", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:46.072Z", "contentLength": 0, "httpStatusCode": 200}	aed523b7-6eae-4983-95db-985653fed4d2	\N	{}	\N	f	f
b7054568-6bba-40f7-81be-ca45f07c8c60	documents	attachments/after_activity_report/67/PN84MFcZKoq0v6OOGIhMBc292efqqooKVFZta300.jpg	\N	2026-08-21 06:23:48.672443+00	2026-08-21 06:23:48.672443+00	2026-08-21 06:23:48.672443+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "image/jpeg", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:48.667Z", "contentLength": 0, "httpStatusCode": 200}	6d66e67f-d3f1-4a4f-9483-8a12434d6e3a	\N	{}	\N	f	f
5242250f-cf8b-4887-a524-c0a70a05be1e	documents	attachments/after_activity_report/67/okJrsrRYoShMFmPUiRie2d1w3qLzFSdQLMr0TWme.pdf	\N	2026-08-21 06:23:50.864764+00	2026-08-21 06:23:50.864764+00	2026-08-21 06:23:50.864764+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:50.859Z", "contentLength": 0, "httpStatusCode": 200}	24070712-410b-4b50-9876-04994bd10cec	\N	{}	\N	f	f
3bfaee51-49c9-434a-9d2e-28cb948d0b35	documents	attachments/after_activity_report/67/oGbnchh6uEo6gBzF2mDXMamTSJ6M2YJ6p9lRJRPc.pdf	\N	2026-08-21 06:23:53.280126+00	2026-08-21 06:23:53.280126+00	2026-08-21 06:23:53.280126+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:53.275Z", "contentLength": 0, "httpStatusCode": 200}	490efdf9-f597-4ab8-985d-f9d30fdf8577	\N	{}	\N	f	f
e6b8cc2d-dda1-4bfc-86c9-0dbced086175	documents	attachments/after_activity_report/68/NebcpqTki6mWy6QSp7VimYQIgRqk67GJRQWb4LO0.jpg	\N	2026-08-21 06:23:55.506346+00	2026-08-21 06:23:55.506346+00	2026-08-21 06:23:55.506346+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "image/jpeg", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:55.501Z", "contentLength": 0, "httpStatusCode": 200}	a9fd50a7-c119-4f1e-919d-66ab2473e06a	\N	{}	\N	f	f
f554dc44-ec79-434e-99c3-c2eaf32e500a	documents	attachments/after_activity_report/68/gkNTwBWwETcOfTq33eyi4Afxt1ueKKpOjm0M4gys.jpg	\N	2026-08-21 06:23:57.798413+00	2026-08-21 06:23:57.798413+00	2026-08-21 06:23:57.798413+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "image/jpeg", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:23:57.793Z", "contentLength": 0, "httpStatusCode": 200}	8b3deb17-97c5-4e17-b3ae-3c382a6aa13c	\N	{}	\N	f	f
7abf96cd-51b0-4c12-ac20-17d914a1d7b4	documents	attachments/after_activity_report/68/FUgYIAlE0Vw6UQy8Vrqp6pO0uDwd2mLMKCwgsMff.pdf	\N	2026-08-21 06:24:00.008793+00	2026-08-21 06:24:00.008793+00	2026-08-21 06:24:00.008793+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:24:00.003Z", "contentLength": 0, "httpStatusCode": 200}	bf7743cf-4408-4fe4-9b4c-e22027395f2c	\N	{}	\N	f	f
ccff371a-c9d9-4d88-b550-1caf2f5d405e	documents	attachments/after_activity_report/68/a6K3nDs3QalSzfXNkwPyttyR4FL5wMsUe0nvdXMl.pdf	\N	2026-08-21 06:24:02.370811+00	2026-08-21 06:24:02.370811+00	2026-08-21 06:24:02.370811+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:24:02.366Z", "contentLength": 0, "httpStatusCode": 200}	f8c21a27-f1bc-4322-818b-3c3a28679161	\N	{}	\N	f	f
87442dd5-3fae-4671-b858-5e9fca6c699f	documents	attachments/after_activity_report/69/Z4p2SJh4Ub228KUI4HCmxwHHJOgmEAqTYeU4nskn.jpg	\N	2026-08-21 06:24:04.611133+00	2026-08-21 06:24:04.611133+00	2026-08-21 06:24:04.611133+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "image/jpeg", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:24:04.606Z", "contentLength": 0, "httpStatusCode": 200}	d497ee66-ff59-4ba1-9e88-598dd307d6a4	\N	{}	\N	f	f
2924ecbb-4321-4093-819d-8669d72edff7	documents	attachments/after_activity_report/69/nh7QUM795sbhjwcdgHRJKDAfbz7Yfw4Qtk0CC4l6.jpg	\N	2026-08-21 06:24:06.810471+00	2026-08-21 06:24:06.810471+00	2026-08-21 06:24:06.810471+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "image/jpeg", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:24:06.805Z", "contentLength": 0, "httpStatusCode": 200}	db1d65e2-af3c-4f48-9ba6-5c78af973207	\N	{}	\N	f	f
da9085f3-c44d-49bc-8c5d-4903af97f2b1	documents	attachments/after_activity_report/69/urWPNkTODQoYIcqfmZGpHxaeqd7QQVwIgXahB1bm.pdf	\N	2026-08-21 06:24:09.049149+00	2026-08-21 06:24:09.049149+00	2026-08-21 06:24:09.049149+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:24:09.044Z", "contentLength": 0, "httpStatusCode": 200}	19939f81-d0a2-4b74-b99c-802e8d3be40e	\N	{}	\N	f	f
ade99ba8-0eeb-4d6d-8f0e-7188208b2300	documents	attachments/after_activity_report/69/GRPC8TiRXrH23xeJp0GFddiRh43ocx1i6GOXUACw.pdf	\N	2026-08-21 06:24:11.284742+00	2026-08-21 06:24:11.284742+00	2026-08-21 06:24:11.284742+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-21T06:24:11.279Z", "contentLength": 0, "httpStatusCode": 200}	5187369a-acc5-4c8d-9ad9-697e4064d27d	\N	{}	\N	f	f
effc08c7-46ce-4200-a70d-75a87a29e473	documents	attachments/organization_registration/71/UqcpuVXsQyPwb4AhNxLwHMfACKPzNjtyLIABRAKd.png	\N	2026-08-24 05:50:23.968068+00	2026-08-24 05:50:23.968068+00	2026-08-24 05:50:23.968068+00	{"eTag": "\\"fcacd0857b6d6328b33887c6442b4cd2\\"", "size": 18719, "mimetype": "image/png", "cacheControl": "no-cache", "lastModified": "2026-08-24T05:50:24.000Z", "contentLength": 18719, "httpStatusCode": 200}	1da22f8f-1243-42bb-8fe4-e934d8d21f36	\N	{}	\N	f	f
75703973-366e-48cb-9f27-0c1ae8409b65	documents	attachments/organization_registration/71/4Xcz6RzNA8J1baAkF71Ysk0JTJUrrXX0yCHL1v14.png	\N	2026-08-24 05:50:24.829403+00	2026-08-24 05:50:24.829403+00	2026-08-24 05:50:24.829403+00	{"eTag": "\\"fcacd0857b6d6328b33887c6442b4cd2\\"", "size": 18719, "mimetype": "image/png", "cacheControl": "no-cache", "lastModified": "2026-08-24T05:50:25.000Z", "contentLength": 18719, "httpStatusCode": 200}	b3029d22-db73-435d-beb4-8b045cdf5b46	\N	{}	\N	f	f
ab47692a-9026-4a48-a710-765033d029bc	documents	attachments/organization_registration/71/jXMsOPc2Gtc18RH9K5vQADyuifY2zKl5No0JfwHk.png	\N	2026-08-24 05:50:26.780948+00	2026-08-24 05:50:26.780948+00	2026-08-24 05:50:26.780948+00	{"eTag": "\\"fcacd0857b6d6328b33887c6442b4cd2\\"", "size": 18719, "mimetype": "image/png", "cacheControl": "no-cache", "lastModified": "2026-08-24T05:50:27.000Z", "contentLength": 18719, "httpStatusCode": 200}	2f01d7d4-7a3e-488b-9257-fbb5aa62b620	\N	{}	\N	f	f
d8d86588-0169-430a-abda-34d92bb278a8	documents	attachments/organization_registration/71/QrH516raQNwPOgsi33K7CeGcMRQ7qEl4KvYDUpAP.png	\N	2026-08-24 05:50:27.773168+00	2026-08-24 05:50:27.773168+00	2026-08-24 05:50:27.773168+00	{"eTag": "\\"fcacd0857b6d6328b33887c6442b4cd2\\"", "size": 18719, "mimetype": "image/png", "cacheControl": "no-cache", "lastModified": "2026-08-24T05:50:28.000Z", "contentLength": 18719, "httpStatusCode": 200}	008dfe8e-1a5e-4260-a428-15fd2bb4d093	\N	{}	\N	f	f
1004177c-6d7d-4775-863b-4f455fe3895c	documents	attachments/organization_registration/71/ojWXVLjburVysGbUCMtIEtoWDR0vDlYZt6uUBmZQ.png	\N	2026-08-24 05:50:28.669969+00	2026-08-24 05:50:28.669969+00	2026-08-24 05:50:28.669969+00	{"eTag": "\\"fcacd0857b6d6328b33887c6442b4cd2\\"", "size": 18719, "mimetype": "image/png", "cacheControl": "no-cache", "lastModified": "2026-08-24T05:50:29.000Z", "contentLength": 18719, "httpStatusCode": 200}	f5001730-519f-4bf2-953f-f455fff7ea16	\N	{}	\N	f	f
9ef21fce-c14c-48d8-81b4-6d94351303d5	documents	attachments/organization_registration/71/FrmVjSrdZX7UaioYOOhR1IlMlxPIhCgJpQKM7Tvm.png	\N	2026-08-24 06:16:17.43033+00	2026-08-24 06:16:17.43033+00	2026-08-24 06:16:17.43033+00	{"eTag": "\\"fcacd0857b6d6328b33887c6442b4cd2\\"", "size": 18719, "mimetype": "image/png", "cacheControl": "no-cache", "lastModified": "2026-08-24T06:16:18.000Z", "contentLength": 18719, "httpStatusCode": 200}	6257883b-b178-4b93-8600-72d72b6540bd	\N	{}	\N	f	f
3435bf27-fe8e-44c0-8281-8d3a669eb8af	documents	attachments/organization_renewal/72/n2KVfiBZtfpr3mJkVmRinLeo63VQyxSGcXKFJixg.png	\N	2026-08-24 06:29:48.874469+00	2026-08-24 06:29:48.874469+00	2026-08-24 06:29:48.874469+00	{"eTag": "\\"fcacd0857b6d6328b33887c6442b4cd2\\"", "size": 18719, "mimetype": "image/png", "cacheControl": "no-cache", "lastModified": "2026-08-24T06:29:49.000Z", "contentLength": 18719, "httpStatusCode": 200}	f34e2dd5-ea5c-4de0-a293-c076004652fa	\N	{}	\N	f	f
e6bd8960-6cce-4c83-ac4f-49a3259dac7b	documents	attachments/organization_renewal/72/JMIPIoHrID1s3bYj8tj7rnLylluT5N5UU0yMGd3o.png	\N	2026-08-24 06:29:49.962036+00	2026-08-24 06:29:49.962036+00	2026-08-24 06:29:49.962036+00	{"eTag": "\\"fcacd0857b6d6328b33887c6442b4cd2\\"", "size": 18719, "mimetype": "image/png", "cacheControl": "no-cache", "lastModified": "2026-08-24T06:29:50.000Z", "contentLength": 18719, "httpStatusCode": 200}	9626a388-e84b-4cb7-926d-2bf90f2595e5	\N	{}	\N	f	f
c707ef07-e8ae-446b-be0c-624fdefe0a87	documents	attachments/organization_renewal/72/ie5rNYkSLqjCSIzGRHfTA7ZZRszy1BxRrWzhSlW9.png	\N	2026-08-24 06:29:50.737097+00	2026-08-24 06:29:50.737097+00	2026-08-24 06:29:50.737097+00	{"eTag": "\\"fcacd0857b6d6328b33887c6442b4cd2\\"", "size": 18719, "mimetype": "image/png", "cacheControl": "no-cache", "lastModified": "2026-08-24T06:29:51.000Z", "contentLength": 18719, "httpStatusCode": 200}	bd49c120-ac90-44d5-82b7-5ced3684411e	\N	{}	\N	f	f
7e18c28c-43ec-4952-afba-8db4b1b93aab	documents	attachments/organization_renewal/72/f8cIyXjlMnB6CBIWH6a1JOuIlV9yNsqBGJx3Hzzl.png	\N	2026-08-24 06:29:51.485854+00	2026-08-24 06:29:51.485854+00	2026-08-24 06:29:51.485854+00	{"eTag": "\\"fcacd0857b6d6328b33887c6442b4cd2\\"", "size": 18719, "mimetype": "image/png", "cacheControl": "no-cache", "lastModified": "2026-08-24T06:29:52.000Z", "contentLength": 18719, "httpStatusCode": 200}	a16c77b5-55b2-4b85-a569-06073eaf65d9	\N	{}	\N	f	f
6fbcaf85-f8ea-4c5d-8f90-226df9d1f431	documents	attachments/organization_renewal/72/M2EtDweUjtoA2BKCKT5BnLkFwrrVtOZeqCA8gP6m.png	\N	2026-08-24 06:29:52.234165+00	2026-08-24 06:29:52.234165+00	2026-08-24 06:29:52.234165+00	{"eTag": "\\"fcacd0857b6d6328b33887c6442b4cd2\\"", "size": 18719, "mimetype": "image/png", "cacheControl": "no-cache", "lastModified": "2026-08-24T06:29:53.000Z", "contentLength": 18719, "httpStatusCode": 200}	2072a660-bcdd-4289-beb7-7bce13f07604	\N	{}	\N	f	f
36d30db6-27a2-4b51-b392-4e866943ed88	documents	attachments/organization_renewal/72/UNsXDUqTd65RQ0mCP4e9ViHdjYpHjqhNb5HPL2jJ.png	\N	2026-08-24 06:29:52.964487+00	2026-08-24 06:29:52.964487+00	2026-08-24 06:29:52.964487+00	{"eTag": "\\"fcacd0857b6d6328b33887c6442b4cd2\\"", "size": 18719, "mimetype": "image/png", "cacheControl": "no-cache", "lastModified": "2026-08-24T06:29:53.000Z", "contentLength": 18719, "httpStatusCode": 200}	f6cc2ced-c3fc-4341-b1c3-067d8e9290b3	\N	{}	\N	f	f
b582c29d-1552-465d-97b3-f9ccc7abd9cf	documents	attachments/organization_renewal/72/iwfJ8MOSYNAkK882zHHms11PAy0AB4RIPkmCWVch.png	\N	2026-08-24 06:29:53.697594+00	2026-08-24 06:29:53.697594+00	2026-08-24 06:29:53.697594+00	{"eTag": "\\"fcacd0857b6d6328b33887c6442b4cd2\\"", "size": 18719, "mimetype": "image/png", "cacheControl": "no-cache", "lastModified": "2026-08-24T06:29:54.000Z", "contentLength": 18719, "httpStatusCode": 200}	b1589c5e-6e61-4ab5-8799-41616e67478d	\N	{}	\N	f	f
ccc5f40a-f861-4b84-8d80-24e7fc523a52	documents	attachments/organization_renewal/72/uXDyzT6lhqGSZ9wyKxnUhYPPxyzKj1xJrcmTYy54.png	\N	2026-08-24 06:29:54.484812+00	2026-08-24 06:29:54.484812+00	2026-08-24 06:29:54.484812+00	{"eTag": "\\"fcacd0857b6d6328b33887c6442b4cd2\\"", "size": 18719, "mimetype": "image/png", "cacheControl": "no-cache", "lastModified": "2026-08-24T06:29:55.000Z", "contentLength": 18719, "httpStatusCode": 200}	8ed379d4-27da-40b9-b5ac-5b4f1cd84eba	\N	{}	\N	f	f
a69de839-ac1d-483f-837c-e1321ffde154	documents	attachments/organization_renewal/72/aHeG6NoSMIjHPk2vQbIpLg8STuzWTGbpNXLQh6DE.png	\N	2026-08-24 06:29:55.430165+00	2026-08-24 06:29:55.430165+00	2026-08-24 06:29:55.430165+00	{"eTag": "\\"fcacd0857b6d6328b33887c6442b4cd2\\"", "size": 18719, "mimetype": "image/png", "cacheControl": "no-cache", "lastModified": "2026-08-24T06:29:56.000Z", "contentLength": 18719, "httpStatusCode": 200}	45e3da46-a68d-4b0a-9a5b-328cd4a74ecf	\N	{}	\N	f	f
6724ab15-e837-4b02-90eb-50f7c06204e8	documents	attachments/organization_registration/73/trBeYHN5DMoRmNVQHG3D91YdtOW3y82xhNebqE9Q.pdf	\N	2026-08-24 08:48:57.936608+00	2026-08-24 08:48:57.936608+00	2026-08-24 08:48:57.936608+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-24T08:48:58.000Z", "contentLength": 121241, "httpStatusCode": 200}	1a970000-911b-42e9-895f-f793ab70f49e	\N	{}	\N	f	f
a1f77505-e2b5-4615-ad25-d3da3cfcaba7	documents	attachments/organization_registration/73/NL5WLDqw6NCB5m29k5vUrKWhXt9isVbaN1vyzbwb.pdf	\N	2026-08-24 08:48:58.813695+00	2026-08-24 08:48:58.813695+00	2026-08-24 08:48:58.813695+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-24T08:48:59.000Z", "contentLength": 121241, "httpStatusCode": 200}	31947e72-a725-472d-bfeb-e9b0a4a2d7e7	\N	{}	\N	f	f
445ffdac-4e22-4de5-98cf-4999e933f77b	documents	attachments/organization_registration/73/RHyBL8Mq7Ud7duCSdSPJNC3C6Kop6oIe8q1W4ZJd.pdf	\N	2026-08-24 08:48:59.791163+00	2026-08-24 08:48:59.791163+00	2026-08-24 08:48:59.791163+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-24T08:49:00.000Z", "contentLength": 121241, "httpStatusCode": 200}	f95349b7-adbd-49ef-af1c-9dee8474a704	\N	{}	\N	f	f
01bb3ef8-00b2-4855-a71f-4509bfd308b9	documents	attachments/organization_registration/73/cEJFOoZN4ruDUm5n6JGg225Mi6efpUBah8YwoClz.pdf	\N	2026-08-24 08:49:00.691398+00	2026-08-24 08:49:00.691398+00	2026-08-24 08:49:00.691398+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-24T08:49:01.000Z", "contentLength": 121241, "httpStatusCode": 200}	78a50705-5790-45d5-9fc4-c97af1119f4d	\N	{}	\N	f	f
ea2851cc-e976-46c9-8715-028d6617c265	documents	attachments/organization_registration/73/WSAkJT0hqAqR6J6UHVwGnkByy9fvMoMeWcdvEyAS.pdf	\N	2026-08-24 08:49:01.565006+00	2026-08-24 08:49:01.565006+00	2026-08-24 08:49:01.565006+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-24T08:49:02.000Z", "contentLength": 121241, "httpStatusCode": 200}	140c474e-ae90-4b42-a294-3918ce664d66	\N	{}	\N	f	f
ad02b3a0-7d2e-4867-94b1-3e34b3810074	documents	attachments/organization_registration/73/Bxtp0ltj1I7aUj7rMUaAxSajJt7cozrrIG3dJB56.pdf	\N	2026-08-24 08:49:02.465029+00	2026-08-24 08:49:02.465029+00	2026-08-24 08:49:02.465029+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-24T08:49:03.000Z", "contentLength": 121241, "httpStatusCode": 200}	b29ca30c-01f2-4bcf-b42b-d0c2a38d51ae	\N	{}	\N	f	f
fc774677-63d7-4fcf-a15f-bea9735c02da	documents	attachments/organization_registration/74/QU2TUCM0Gh2CLVVKJFr3LiBJulmQaLUNqqLgOwTs.pdf	\N	2026-08-24 09:12:06.438126+00	2026-08-24 09:12:06.438126+00	2026-08-24 09:12:06.438126+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-24T09:12:07.000Z", "contentLength": 121241, "httpStatusCode": 200}	02f8fa3e-4c10-4792-a4b5-a3b3b8108552	\N	{}	\N	f	f
8b81b9e8-368d-4c4c-8d7c-8d9df61527bb	documents	attachments/organization_registration/74/OrmQdAUVTW6YGmpjrFSN5gap1umfhiBRL6BwDOFs.pdf	\N	2026-08-24 09:12:07.35889+00	2026-08-24 09:12:07.35889+00	2026-08-24 09:12:07.35889+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-24T09:12:08.000Z", "contentLength": 121241, "httpStatusCode": 200}	b6f961fe-abd4-4e7e-a4eb-0f4fa022394e	\N	{}	\N	f	f
7cb5c208-6e6a-4703-9edb-21f33f332008	documents	attachments/organization_registration/74/qKPUvfKmsRG5POwgvlJJbTq7mQ15rPVHmmfHD1d9.pdf	\N	2026-08-24 09:12:08.562146+00	2026-08-24 09:12:08.562146+00	2026-08-24 09:12:08.562146+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-24T09:12:09.000Z", "contentLength": 121241, "httpStatusCode": 200}	b58db3ea-077a-4775-baf3-5900d13f7509	\N	{}	\N	f	f
7802bdbe-2674-421f-9380-7d7edb0c1fb1	documents	attachments/organization_registration/74/PHUw33p2ocxXGRJdHekwvchJvYN7y2ZCssQ40VNL.pdf	\N	2026-08-24 09:12:09.467976+00	2026-08-24 09:12:09.467976+00	2026-08-24 09:12:09.467976+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-24T09:12:10.000Z", "contentLength": 121241, "httpStatusCode": 200}	885f5c92-68cd-4666-8612-8bbdc7094f15	\N	{}	\N	f	f
461fbf35-d5e8-4abb-a80c-071941f33458	documents	attachments/organization_registration/74/cr4oiQ7EFnBq1DAqxikP6TKm7Kw4KgWL8wfQ79Uc.pdf	\N	2026-08-24 09:12:10.379209+00	2026-08-24 09:12:10.379209+00	2026-08-24 09:12:10.379209+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-24T09:12:11.000Z", "contentLength": 121241, "httpStatusCode": 200}	4bd41c39-379a-49ad-8549-5d1045834fd9	\N	{}	\N	f	f
969e65d2-c6c5-431c-8a24-5ba78abc47b4	documents	attachments/organization_registration/74/vmwxdfJu2nb9cQhwGb9hkA4b1js3vdpf8Np7KRqO.pdf	\N	2026-08-24 09:27:54.551327+00	2026-08-24 09:27:54.551327+00	2026-08-24 09:27:54.551327+00	{"eTag": "\\"ac38d80365aeef760c5d21e6e37dcfc0\\"", "size": 175878, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-24T09:27:55.000Z", "contentLength": 175878, "httpStatusCode": 200}	c091b00d-8c7c-41b3-bd5c-8c33101d6bba	\N	{}	\N	f	f
f82888f0-f81f-4692-a8dc-7ee64e83a47b	documents	attachments/activity_proposal/78/OUXsTeKYeVm0HRiNbPs6j0AMLoiKdpqsdKXHuER4.pdf	\N	2026-08-24 13:06:16.098397+00	2026-08-24 13:06:16.098397+00	2026-08-24 13:06:16.098397+00	{"eTag": "\\"bea1b3550096c71de97dae7df2941bec\\"", "size": 128552, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-24T13:06:17.000Z", "contentLength": 128552, "httpStatusCode": 200}	f795dc39-f326-4790-ba79-6eb4c42d0051	\N	{}	\N	f	f
091e5a8e-2f11-4f59-8154-734cd70b3f6e	documents	attachments/activity_proposal/79/YHbrU5ujWbvbOjRKJNBhWX7QtlJuT4hUXeXxVdx9.pdf	\N	2026-08-24 13:54:51.524582+00	2026-08-24 13:54:51.524582+00	2026-08-24 13:54:51.524582+00	{"eTag": "\\"bea1b3550096c71de97dae7df2941bec\\"", "size": 128552, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-24T13:54:52.000Z", "contentLength": 128552, "httpStatusCode": 200}	5cdcc9f4-915f-4a4c-8896-e9a8df8fd7f3	\N	{}	\N	f	f
dd9dfb65-0855-49df-901f-8c8d279a6a64	documents	attachments/activity_proposal/80/ruREvk6lHMNOaIEmCDYt3koY7KV1tQ3pYyGNpR8N.pdf	\N	2026-08-24 16:22:38.706077+00	2026-08-24 16:22:38.706077+00	2026-08-24 16:22:38.706077+00	{"eTag": "\\"bea1b3550096c71de97dae7df2941bec\\"", "size": 128552, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-24T16:22:39.000Z", "contentLength": 128552, "httpStatusCode": 200}	acbbaade-a307-4fb9-87a6-dee607179fe2	\N	{}	\N	f	f
5fe85165-41a0-4b75-b041-5c31eb416250	documents	attachments/after_activity_report/81/9ZhZAqr7kpN6bSGitc5rwZPDyG2b8XljzegDLVLf.png	\N	2026-08-24 17:06:07.15468+00	2026-08-24 17:06:07.15468+00	2026-08-24 17:06:07.15468+00	{"eTag": "\\"b8e0ab5a55e7655c0975e983e0be606b\\"", "size": 280036, "mimetype": "image/png", "cacheControl": "no-cache", "lastModified": "2026-08-24T17:06:08.000Z", "contentLength": 280036, "httpStatusCode": 200}	2b1c51d6-4d38-4ccc-a9e3-5de232109d80	\N	{}	\N	f	f
02928de6-0615-403a-868c-d845cf2be46e	documents	attachments/after_activity_report/81/28Wf141yzvQEeL1Dy3QPZAZyh6o0iHBrS8ckH8zf.pdf	\N	2026-08-24 17:06:08.29849+00	2026-08-24 17:06:08.29849+00	2026-08-24 17:06:08.29849+00	{"eTag": "\\"bea1b3550096c71de97dae7df2941bec\\"", "size": 128552, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-24T17:06:09.000Z", "contentLength": 128552, "httpStatusCode": 200}	9583985c-acbb-44d2-acca-e68a95226038	\N	{}	\N	f	f
552158ab-1513-4624-9d25-253779085e3e	documents	attachments/after_activity_report/81/rt1tr50WypFt1weZogYLvQ1qpRZh1CrvUloan5zQ.pdf	\N	2026-08-24 17:06:09.163295+00	2026-08-24 17:06:09.163295+00	2026-08-24 17:06:09.163295+00	{"eTag": "\\"bea1b3550096c71de97dae7df2941bec\\"", "size": 128552, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-24T17:06:10.000Z", "contentLength": 128552, "httpStatusCode": 200}	db72f28c-eb61-423e-8a8f-8fb86ff535b3	\N	{}	\N	f	f
a7aaa062-f71e-441b-81c1-d8e964f7fdb2	documents	attachments/organization_registration/37/3QhAIsJcYdrYhWZsWakdY2s7VkvKLUcn4XCdv5yG.pdf	\N	2026-08-29 13:15:13.069288+00	2026-08-29 13:15:13.069288+00	2026-08-29 13:15:13.069288+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-29T13:15:13.067Z", "contentLength": 0, "httpStatusCode": 200}	a5132480-df48-441b-a6ec-fa3a36308c23	\N	{}	\N	f	f
56a0c36a-9996-43f9-bdc0-fc76e829f0b8	documents	attachments/organization_registration/37/o1eeK45J38eapBUkYeSP9lfV82qwB9gIe3OGSZWt.pdf	\N	2026-08-29 13:15:13.556065+00	2026-08-29 13:15:13.556065+00	2026-08-29 13:15:13.556065+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-29T13:15:13.554Z", "contentLength": 0, "httpStatusCode": 200}	6ffb05e8-aec4-438b-b6d3-813bf37dcafa	\N	{}	\N	f	f
f2a50150-9485-4ef1-8574-b440ac182c44	documents	attachments/organization_registration/37/qPCESLQhqd3m8ZFvkcoTkORaCJ1fC4dCXvSgRiZF.pdf	\N	2026-08-29 13:15:13.831701+00	2026-08-29 13:15:13.831701+00	2026-08-29 13:15:13.831701+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-29T13:15:13.829Z", "contentLength": 0, "httpStatusCode": 200}	8cce5d9a-da77-4d1c-94dc-fea73b4eca6b	\N	{}	\N	f	f
0568eec8-fd48-4956-90bd-a1ffcd32eec1	documents	attachments/organization_registration/37/itkxWVtJjspm1IOf7Vs1Yxp13p54JCRkjko4zMIY.pdf	\N	2026-08-29 13:15:14.060164+00	2026-08-29 13:15:14.060164+00	2026-08-29 13:15:14.060164+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-29T13:15:14.058Z", "contentLength": 0, "httpStatusCode": 200}	e0c935d2-a3cb-468b-b04f-0d783e551abd	\N	{}	\N	f	f
f65e4e6a-6dec-4075-8f55-a61b128f07f7	documents	attachments/organization_registration/37/feNj3LcySjNZmWxqghJ7ZRJjTOQYS1fBwWmeZkT5.pdf	\N	2026-08-29 13:15:14.648897+00	2026-08-29 13:15:14.648897+00	2026-08-29 13:15:14.648897+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-29T13:15:14.646Z", "contentLength": 0, "httpStatusCode": 200}	4f054191-29a9-4aeb-86d9-56902b79fe17	\N	{}	\N	f	f
77b68868-3cf8-47ed-a74d-44f698a7a904	documents	attachments/organization_registration/37/CQ7oZ5KVYMMUcFpxerTk9Qn69o0Q8PeNsH3ZgKWn.pdf	\N	2026-08-29 13:15:14.982597+00	2026-08-29 13:15:14.982597+00	2026-08-29 13:15:14.982597+00	{"eTag": "\\"d41d8cd98f00b204e9800998ecf8427e\\"", "size": 0, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-29T13:15:14.980Z", "contentLength": 0, "httpStatusCode": 200}	17577383-beb3-4fc5-a88a-f9fc929378b4	\N	{}	\N	f	f
03cd8552-4731-4bc9-9597-5a765c2639d7	documents	attachments/organization_registration/82/HzIjaXU203L9qGW4cHwANzRVdY6x3UVL8fzkAGwB.pdf	\N	2026-08-29 14:31:51.988983+00	2026-08-29 14:31:51.988983+00	2026-08-29 14:31:51.988983+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-29T14:31:52.000Z", "contentLength": 121241, "httpStatusCode": 200}	1d766fd4-d2fa-40b8-9977-8cf52b14ae49	\N	{"mtime": "1788012943"}	\N	f	f
e21432cf-6228-4bb7-91d0-84dc07d8cdd0	documents	attachments/organization_registration/82/YWNBRJeAGZWNnJFKzNE5N57rukZMul5ypkpRZgJJ.pdf	\N	2026-08-29 14:31:52.058259+00	2026-08-29 14:31:52.058259+00	2026-08-29 14:31:52.058259+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-29T14:31:52.000Z", "contentLength": 107792, "httpStatusCode": 200}	53067abd-8085-44b2-8f58-8bc90adced4b	\N	{"mtime": "1788012948"}	\N	f	f
ce7d3cf1-45c3-4559-80ab-3bd2c2b6e59c	documents	attachments/organization_registration/82/0JjCEesVNV57tKbWj2H9iMm27OZ0LS9ugYdTndky.pdf	\N	2026-08-29 14:31:52.151639+00	2026-08-29 14:31:52.151639+00	2026-08-29 14:31:52.151639+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-29T14:31:53.000Z", "contentLength": 107792, "httpStatusCode": 200}	d8ea13ee-3654-4d41-98fa-f8064d91eeb0	\N	{"mtime": "1788012944"}	\N	f	f
083dd188-62cc-4270-88e9-ae5b749b460a	documents	attachments/organization_registration/82/EDs1F8xV6cpyGEwOQygHqcAOvDDShJKrdDWNfszL.pdf	\N	2026-08-29 14:31:52.222228+00	2026-08-29 14:31:52.222228+00	2026-08-29 14:31:52.222228+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-29T14:31:53.000Z", "contentLength": 107792, "httpStatusCode": 200}	36ad61ca-1689-4d8b-a455-ab29bc0b0627	\N	{"mtime": "1788012945"}	\N	f	f
d7b0f72c-ea81-470f-b2c8-db5274fb38c3	documents	attachments/organization_registration/82/qNeFECIveAMLfxrAwYCvkHfwWKHZhyKBLffbvD8K.pdf	\N	2026-08-29 14:31:52.472574+00	2026-08-29 14:31:52.472574+00	2026-08-29 14:31:52.472574+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-29T14:31:53.000Z", "contentLength": 107792, "httpStatusCode": 200}	1af7b247-6e2b-47b4-aee3-c046109a3c1c	\N	{"mtime": "1788012947"}	\N	f	f
3cc7854f-09a4-4c29-8ee5-c466a81e0a3b	documents	attachments/organization_registration/82/rbGT8ljlwCCGikoNNzSMkw9iKRSfDixySchiovXS.pdf	\N	2026-08-29 14:31:52.891931+00	2026-08-29 14:31:52.891931+00	2026-08-29 14:31:52.891931+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-29T14:31:53.000Z", "contentLength": 107792, "httpStatusCode": 200}	6f53cf72-c955-4271-b309-b83e3e7ccdbc	\N	{"mtime": "1788012946"}	\N	f	f
7ee4ad2e-db9f-4ecc-ae2a-0a42756ad640	documents	attachments/organization_registration/83/shyhKtJN0rkc9V4OVJMyqfWVsYiWzoOXvMhz9r7B.pdf	\N	2026-08-30 02:54:04.953485+00	2026-08-30 02:54:04.953485+00	2026-08-30 02:54:04.953485+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T02:54:05.000Z", "contentLength": 107792, "httpStatusCode": 200}	2f16e696-5b64-43f0-a8b1-fdf78ef73a28	\N	{}	\N	f	f
9ea94082-e4c0-44b3-a791-ceabf1f0f58e	documents	attachments/organization_registration/83/4krzmeEz255ugnBXBr0IB1snAaZafJhtG9cSNhRd.pdf	\N	2026-08-30 02:54:05.111688+00	2026-08-30 02:54:05.111688+00	2026-08-30 02:54:05.111688+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T02:54:06.000Z", "contentLength": 107792, "httpStatusCode": 200}	50d54eb3-aafe-47f3-b41d-bb9c5f5d872f	\N	{}	\N	f	f
b490ba61-a547-48e8-afb7-819cf030fa68	documents	attachments/organization_registration/83/fv7cefCoNOUdJtXENl4d0yV3i8nPK1O2WEHwloaM.pdf	\N	2026-08-30 02:54:05.23298+00	2026-08-30 02:54:05.23298+00	2026-08-30 02:54:05.23298+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T02:54:06.000Z", "contentLength": 107792, "httpStatusCode": 200}	f209045f-f40f-41dc-a4d3-a02933542454	\N	{}	\N	f	f
2e8a8996-e8bf-4f00-90b5-0ae013c52982	documents	attachments/organization_registration/83/bCUAiMruynxDzUJHKzCNmgj7gqs5aaVE8orTPeQd.pdf	\N	2026-08-30 02:54:05.343319+00	2026-08-30 02:54:05.343319+00	2026-08-30 02:54:05.343319+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T02:54:06.000Z", "contentLength": 107792, "httpStatusCode": 200}	dbc5ea05-a94a-4fed-9ec7-cfb64e83ba28	\N	{}	\N	f	f
d73ef4e2-ddd8-4c6c-81e4-268d7ccaaa1e	documents	attachments/organization_registration/83/Ptjvh32cvPRhUX7PKRxTb9T29RpcSwbRpSfN66eg.pdf	\N	2026-08-30 02:54:05.460725+00	2026-08-30 02:54:05.460725+00	2026-08-30 02:54:05.460725+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T02:54:06.000Z", "contentLength": 107792, "httpStatusCode": 200}	e2f31933-f221-4b38-b367-214632ac2953	\N	{}	\N	f	f
c18337b4-8702-4646-a76c-aacc31cea34c	documents	attachments/organization_registration/83/NAnS39SnMlz2BV4q9C2uryAlUYHG1QaKoCv7lQUM.pdf	\N	2026-08-30 02:54:06.039949+00	2026-08-30 02:54:06.039949+00	2026-08-30 02:54:06.039949+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T02:54:06.000Z", "contentLength": 107792, "httpStatusCode": 200}	2befdd8d-4d10-4afe-b0e7-1fc0a698badf	\N	{}	\N	f	f
806b989a-951f-4ab4-bb36-f6c8f45cd28c	documents	attachments/organization_registration/84/IG8MSwApotzuadTxj5bKsszGGsMoRfAeKrto6dAI.pdf	\N	2026-08-30 03:46:43.006311+00	2026-08-30 03:46:43.006311+00	2026-08-30 03:46:43.006311+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T03:46:43.000Z", "contentLength": 107792, "httpStatusCode": 200}	e6ca4c1e-1bfc-440e-a89d-6873ee6a55a7	\N	{}	\N	f	f
b5d722b6-5eec-4670-8840-b4da4df6ba00	documents	attachments/organization_registration/84/5Z8UFPDEVx0Z1oBewUgXm3XZ7xyMQihddfDqPd2W.pdf	\N	2026-08-30 03:46:43.313183+00	2026-08-30 03:46:43.313183+00	2026-08-30 03:46:43.313183+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T03:46:44.000Z", "contentLength": 107792, "httpStatusCode": 200}	7fbaaf39-bf03-4c34-8cb5-01c79cbccc8e	\N	{}	\N	f	f
1f675e19-fe48-428e-af84-dd782c4f0d72	documents	attachments/organization_registration/84/0iCYaqBUv6QTA71KAaW8vQSdMmVuU1stcK6t8Msn.pdf	\N	2026-08-30 03:46:43.814263+00	2026-08-30 03:46:43.814263+00	2026-08-30 03:46:43.814263+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T03:46:44.000Z", "contentLength": 107792, "httpStatusCode": 200}	b8391aab-fded-49a6-aa7d-9faa7e335444	\N	{}	\N	f	f
c27373b9-272e-4f40-b601-907ff7e9a98a	documents	attachments/organization_registration/84/1NvclFknDyjPoWXy5xcESZVKrQgGkO537Xo2cCwf.pdf	\N	2026-08-30 03:51:11.669328+00	2026-08-30 03:51:11.669328+00	2026-08-30 03:51:11.669328+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T03:51:12.000Z", "contentLength": 121241, "httpStatusCode": 200}	b6514918-a4f8-478f-9786-fa9909981adb	\N	{}	\N	f	f
ae7b7578-334d-4244-8ac0-b5daadde37ae	documents	attachments/organization_registration/84/F2L0bmJNhkkmTvkLJPDhJVDZOZWgHhk3tuNh0I9q.pdf	\N	2026-08-30 03:51:11.95788+00	2026-08-30 03:51:11.95788+00	2026-08-30 03:51:11.95788+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T03:51:12.000Z", "contentLength": 121241, "httpStatusCode": 200}	5ff3c7ec-06b2-4de5-9640-be9f0003ea03	\N	{}	\N	f	f
80186556-42b3-462f-8899-1ed28fd1f9b4	documents	attachments/organization_registration/84/zQR3IRo9hxD5lDgDHteCT6Bfjr48FjjkGrOwIJaL.pdf	\N	2026-08-30 03:51:12.189035+00	2026-08-30 03:51:12.189035+00	2026-08-30 03:51:12.189035+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T03:51:13.000Z", "contentLength": 121241, "httpStatusCode": 200}	3278fb67-b2a9-4cd1-b8ac-375c20445014	\N	{}	\N	f	f
5114fe6a-d402-4b6a-8426-ac6263a8e306	documents	attachments/organization_registration/85/1m7j0GzD0kP44cUkzQYLRjJEHvArTT5Oqo8yrqPb.pdf	\N	2026-08-30 04:13:47.605959+00	2026-08-30 04:13:47.605959+00	2026-08-30 04:13:47.605959+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T04:13:48.000Z", "contentLength": 107792, "httpStatusCode": 200}	1b486299-3517-4085-99d2-29b10293f8ad	\N	{}	\N	f	f
2a989ae4-f1d4-4d41-bcb5-7d00e2b46d70	documents	attachments/organization_registration/85/j2j8CEHDA5wcErzkDKAu8o3qwytCrK1ohbaYT0mC.pdf	\N	2026-08-30 04:13:47.736114+00	2026-08-30 04:13:47.736114+00	2026-08-30 04:13:47.736114+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T04:13:48.000Z", "contentLength": 107792, "httpStatusCode": 200}	d50166fa-fc45-4df9-8256-77d57eba354e	\N	{}	\N	f	f
2e1cd31b-e8f7-49ec-8fb5-bceeea8f8bd6	documents	attachments/organization_registration/85/1SkEUpkhvHgRVJ0JxcJzLiTvkzLDrU1yQArXiUxZ.pdf	\N	2026-08-30 04:13:47.848746+00	2026-08-30 04:13:47.848746+00	2026-08-30 04:13:47.848746+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T04:13:48.000Z", "contentLength": 107792, "httpStatusCode": 200}	35338da6-93ee-40da-a9b3-70e80c3a19d5	\N	{}	\N	f	f
7d2651db-6719-46d2-b7ef-2571089f2ce0	documents	attachments/organization_registration/85/JFuW5gQ1mIfOLDiJaOFH2rpiy6p1JLcIUhk7lopU.pdf	\N	2026-08-30 04:13:47.966702+00	2026-08-30 04:13:47.966702+00	2026-08-30 04:13:47.966702+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T04:13:48.000Z", "contentLength": 107792, "httpStatusCode": 200}	610d12bb-4f16-4cc2-9184-b370e617f037	\N	{}	\N	f	f
83670ba8-72a6-42ce-9079-24425e002198	documents	attachments/organization_registration/85/6EU25TAM7tkGwH9kmqkJCFWeHIevHCwBYOk46GCJ.pdf	\N	2026-08-30 04:13:48.0855+00	2026-08-30 04:13:48.0855+00	2026-08-30 04:13:48.0855+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T04:13:49.000Z", "contentLength": 107792, "httpStatusCode": 200}	b9323bd4-385b-4423-a98d-a7afae0ace2b	\N	{}	\N	f	f
a0a752fd-6209-4bd0-87f3-d28a9eaffd76	documents	attachments/organization_registration/85/y22CM8wXMGUdBLZbFTHGLpNJphZ5S5zwTT9GpAN5.pdf	\N	2026-08-30 04:13:48.193106+00	2026-08-30 04:13:48.193106+00	2026-08-30 04:13:48.193106+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T04:13:49.000Z", "contentLength": 107792, "httpStatusCode": 200}	88a9c89e-1b4b-4efe-8056-24bf429bb04a	\N	{}	\N	f	f
18d63177-0061-42a3-99d9-a122ba5cf247	documents	attachments/organization_registration/86/NivsjQWBcWyFMFeudlcOuCVTOSkeLHKtYZUlGivx.pdf	\N	2026-08-30 04:16:09.249399+00	2026-08-30 04:16:09.249399+00	2026-08-30 04:16:09.249399+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T04:16:10.000Z", "contentLength": 121241, "httpStatusCode": 200}	ddc7f478-fc21-4d03-a5ca-6dae4e3d2f82	\N	{}	\N	f	f
dbc29f22-c030-4a6b-b376-d764707f7091	documents	attachments/organization_registration/86/pcp93cDz25dBMSk77u5cg9K7ZxRRzZVHjprtobaa.pdf	\N	2026-08-30 04:16:09.366753+00	2026-08-30 04:16:09.366753+00	2026-08-30 04:16:09.366753+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T04:16:10.000Z", "contentLength": 121241, "httpStatusCode": 200}	c78ffe75-c8f4-405c-9277-2728b023eb01	\N	{}	\N	f	f
aa94e9e1-0394-4893-a992-97daaea65b19	documents	attachments/organization_registration/86/PRGq2dZvOtd3lYXGPo34fispCZS8QHgIAkZ6sH7U.pdf	\N	2026-08-30 04:16:09.501165+00	2026-08-30 04:16:09.501165+00	2026-08-30 04:16:09.501165+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T04:16:10.000Z", "contentLength": 121241, "httpStatusCode": 200}	9a8e00e8-93fb-49fa-80c4-1980ba14db2e	\N	{}	\N	f	f
408d8083-778e-4fb8-90d5-5d54d0d2bdf1	documents	attachments/organization_registration/86/ZHyT26qxLfTf9k12zMKpUzn65OWZAzcEz5rDo2yr.pdf	\N	2026-08-30 04:16:09.62374+00	2026-08-30 04:16:09.62374+00	2026-08-30 04:16:09.62374+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T04:16:10.000Z", "contentLength": 121241, "httpStatusCode": 200}	8516636f-448c-4b43-9e9f-a268a6db921f	\N	{}	\N	f	f
fd103293-a634-44e2-8c62-a529dfd75d4f	documents	attachments/organization_registration/86/BV3OsuXR9wXT2saMGz0wlo20NLZWR8OCebrxj8XB.pdf	\N	2026-08-30 04:16:09.755695+00	2026-08-30 04:16:09.755695+00	2026-08-30 04:16:09.755695+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T04:16:10.000Z", "contentLength": 121241, "httpStatusCode": 200}	72e103ca-b214-45cf-881e-81e107cd8c2e	\N	{}	\N	f	f
7b43cd59-a3c5-49fe-98c8-23324bb02abc	documents	attachments/organization_registration/86/obyfPR1XUtx0bNW9AcnYRa5ummYSkCGHQ9Jmc1Mq.pdf	\N	2026-08-30 04:16:09.88808+00	2026-08-30 04:16:09.88808+00	2026-08-30 04:16:09.88808+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T04:16:10.000Z", "contentLength": 121241, "httpStatusCode": 200}	25d7fd6e-97dd-479c-9af9-767f762d5d31	\N	{}	\N	f	f
5248a79f-44ed-4a39-9912-91c22a70de93	documents	attachments/organization_registration/87/jTxWQbmPikDet28Yt5Kj3isViuudrKIwk1u9GSuD.pdf	\N	2026-08-30 08:42:33.397311+00	2026-08-30 08:42:33.397311+00	2026-08-30 08:42:33.397311+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T08:42:34.000Z", "contentLength": 121241, "httpStatusCode": 200}	e0b324bc-df04-4bc8-b927-aec3293b5d8e	\N	{}	\N	f	f
b017b56a-e829-4f30-bd12-3bde79aea12f	documents	attachments/organization_registration/87/aMRURd1jH9jy9Hk1kRB0z04SeAAlDGd3Bc9nv2uE.pdf	\N	2026-08-30 08:42:33.748384+00	2026-08-30 08:42:33.748384+00	2026-08-30 08:42:33.748384+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T08:42:34.000Z", "contentLength": 121241, "httpStatusCode": 200}	5b5f663e-e9f0-4b21-86d9-3aa52dcb7431	\N	{}	\N	f	f
5e6d9316-6751-42db-82de-513206aa93de	documents	attachments/organization_registration/87/SJW49xqzy5xvaprCPyO1EaJjcmUln9vnrQtmz1RO.pdf	\N	2026-08-30 08:42:33.98535+00	2026-08-30 08:42:33.98535+00	2026-08-30 08:42:33.98535+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T08:42:34.000Z", "contentLength": 121241, "httpStatusCode": 200}	e2c39cee-a505-437e-a1ef-d0e29d7a7128	\N	{}	\N	f	f
69744689-5c53-4a0e-ac40-0906cd12c94d	documents	attachments/organization_registration/87/thi2K5pY9NZYTYS8UIYAtnhQOcFbIxgT4orx8zkb.pdf	\N	2026-08-30 08:44:26.567328+00	2026-08-30 08:44:26.567328+00	2026-08-30 08:44:26.567328+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T08:44:27.000Z", "contentLength": 121241, "httpStatusCode": 200}	0731cdad-817b-4509-acf9-dc42dded8f41	\N	{}	\N	f	f
34f9d146-158a-461b-9363-7006cccc9880	documents	attachments/organization_registration/87/U0zPsQeSR8YqA58xJHAQwGchnYC2YbFIafAcGJuX.pdf	\N	2026-08-30 08:44:26.873443+00	2026-08-30 08:44:26.873443+00	2026-08-30 08:44:26.873443+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T08:44:27.000Z", "contentLength": 121241, "httpStatusCode": 200}	48cccd87-e4a3-4b98-83b3-850e62773e7f	\N	{}	\N	f	f
060a35d5-3ef6-469b-b2cb-7f6672254dfd	documents	attachments/organization_registration/87/8WZXmoBfYP6ntzHKaHCTGVxyvnSHH1WO7qL0uXwy.pdf	\N	2026-08-30 08:44:27.153785+00	2026-08-30 08:44:27.153785+00	2026-08-30 08:44:27.153785+00	{"eTag": "\\"a2b645bc87860568d0cc16c6acc3e8f3\\"", "size": 121241, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T08:44:28.000Z", "contentLength": 121241, "httpStatusCode": 200}	a86bc0b2-203e-4596-9194-b88f7d81587c	\N	{}	\N	f	f
585e985c-53a6-4d80-a89f-ad63abeb8e54	documents	attachments/organization_registration/88/dlbT6PfryUnM46o5EAKeniRU8AsiQ839z9lE4T1z.pdf	\N	2026-08-30 14:41:17.082088+00	2026-08-30 14:41:17.082088+00	2026-08-30 14:41:17.082088+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T14:41:18.000Z", "contentLength": 107792, "httpStatusCode": 200}	e4f4bcec-1990-4a1d-b0e7-e75d2e00d401	\N	{}	\N	f	f
34231307-fb25-433f-8891-749397f3b39b	documents	attachments/organization_registration/88/f3WpITigAxve2bHbNmHK3bRHn5SwtWohZMbfgHOb.pdf	\N	2026-08-30 14:41:17.233643+00	2026-08-30 14:41:17.233643+00	2026-08-30 14:41:17.233643+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T14:41:18.000Z", "contentLength": 107792, "httpStatusCode": 200}	b2798670-1f0e-4e81-b815-2b101112f831	\N	{}	\N	f	f
76469e59-2e41-4c54-8dab-dfaf84c18a2f	documents	attachments/organization_registration/88/RgRVPyFu9p7YrRBq1ceLMZFVAWlV2Cqu6gc0awrA.pdf	\N	2026-08-30 14:41:19.169564+00	2026-08-30 14:41:19.169564+00	2026-08-30 14:41:19.169564+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T14:41:19.000Z", "contentLength": 107792, "httpStatusCode": 200}	7a693bbe-4a54-4615-b0be-2a2512c41d0b	\N	{}	\N	f	f
ebce0886-4a65-46bd-8509-89629170963c	documents	attachments/organization_registration/88/I6LT3jpQ9K2MbbXYj6ynPBjatz8fiwfouT7wCxJn.pdf	\N	2026-08-30 14:41:19.897235+00	2026-08-30 14:41:19.897235+00	2026-08-30 14:41:19.897235+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T14:41:20.000Z", "contentLength": 107792, "httpStatusCode": 200}	946d1ba0-47d0-45aa-90a5-973d08a75df9	\N	{}	\N	f	f
17dd9065-a4af-4c5e-9034-0eecfac1e482	documents	attachments/organization_registration/88/4j4NbeZW5HjnxFXPuTyPCUArZ9cOpfZl9r24hGcx.pdf	\N	2026-08-30 14:41:20.203203+00	2026-08-30 14:41:20.203203+00	2026-08-30 14:41:20.203203+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T14:41:20.000Z", "contentLength": 107792, "httpStatusCode": 200}	9147d814-f744-438c-9263-c5705c26bfb6	\N	{}	\N	f	f
8ca1cfd2-e64d-48c3-b051-9da847da8995	documents	attachments/organization_registration/88/PM9khmcYpuao0don42mcEGudwInHJG1gBUMO7Dte.pdf	\N	2026-08-30 14:41:20.506673+00	2026-08-30 14:41:20.506673+00	2026-08-30 14:41:20.506673+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T14:41:21.000Z", "contentLength": 107792, "httpStatusCode": 200}	4b49e25a-53cb-44b7-8488-500f375ce73d	\N	{}	\N	f	f
c230ea0b-ec3e-400e-84b0-9f7d9213ab0c	documents	attachments/organization_renewal/89/V4U7uIxdsFWO3zVUVYI6td1fYvOl9rRuj0UR6l9P.pdf	\N	2026-08-30 14:53:31.980674+00	2026-08-30 14:53:31.980674+00	2026-08-30 14:53:31.980674+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T14:53:32.000Z", "contentLength": 107792, "httpStatusCode": 200}	ba9f577b-cf52-44a9-9203-75c8627ead0d	\N	{}	\N	f	f
43c1f425-14a3-4c53-a8dc-a8b0870b4e5d	documents	attachments/organization_renewal/89/PAJ1zwaunsg8c7DcAQ8Uxbt76Uuh2DlFBpVv208t.pdf	\N	2026-08-30 14:53:32.177691+00	2026-08-30 14:53:32.177691+00	2026-08-30 14:53:32.177691+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T14:53:33.000Z", "contentLength": 107792, "httpStatusCode": 200}	fbf70baf-472d-4b25-bc70-5adeadade701	\N	{}	\N	f	f
d962b97b-dc26-4e47-9efd-78fb5afaf040	documents	attachments/organization_renewal/89/WVgntfiLYD7iS9frys3lHiUVrzpaCZrDkCYjCJSw.pdf	\N	2026-08-30 14:53:32.401268+00	2026-08-30 14:53:32.401268+00	2026-08-30 14:53:32.401268+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T14:53:33.000Z", "contentLength": 107792, "httpStatusCode": 200}	13328c4d-cbad-4ec9-ac07-874ecee36f2a	\N	{}	\N	f	f
9769379b-71ac-4994-836d-96060e8b6166	documents	attachments/organization_renewal/89/nSq1FTMCnryyMJRAoKGEOh8yGOLsJUVPQO1fLpPb.pdf	\N	2026-08-30 14:53:32.788395+00	2026-08-30 14:53:32.788395+00	2026-08-30 14:53:32.788395+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T14:53:33.000Z", "contentLength": 107792, "httpStatusCode": 200}	1a92496b-c544-4a17-bb97-37af566dd54f	\N	{}	\N	f	f
fbd7f043-7d63-4e80-9fa1-2f3170e3b731	documents	attachments/organization_renewal/89/NMA0T4zLlwak0LMFT3LLCxK6oO9mKo8Pzydk9FoJ.pdf	\N	2026-08-30 14:53:32.93702+00	2026-08-30 14:53:32.93702+00	2026-08-30 14:53:32.93702+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T14:53:33.000Z", "contentLength": 107792, "httpStatusCode": 200}	a6056d6e-0534-4abd-8e84-94bbe3b237a3	\N	{}	\N	f	f
b11c6539-4116-401c-a406-818c9a3d09c8	documents	attachments/organization_renewal/89/o886FLSha3mMwlZBlnP6ZkZy8pQP1j0xArJN9ktn.pdf	\N	2026-08-30 14:53:33.048128+00	2026-08-30 14:53:33.048128+00	2026-08-30 14:53:33.048128+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T14:53:34.000Z", "contentLength": 107792, "httpStatusCode": 200}	a44ad3bb-37a9-4dc6-9cd4-3896433be0e3	\N	{}	\N	f	f
970ad6aa-a93c-4ec6-b413-a270c0b3734f	documents	attachments/organization_renewal/89/FxVBMa64ZPHPEOuN53PwtmDUroT7gk0FtDCycyo9.pdf	\N	2026-08-30 14:53:33.169281+00	2026-08-30 14:53:33.169281+00	2026-08-30 14:53:33.169281+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T14:53:34.000Z", "contentLength": 107792, "httpStatusCode": 200}	16f5bd06-0906-467a-95ca-a7182ae03d4c	\N	{}	\N	f	f
088e0f73-b713-4668-94bb-43bd779fd2df	documents	attachments/organization_renewal/89/bthyWLMbIdVaUGdGwQEEDknXjODrUJe9amzh8cxY.pdf	\N	2026-08-30 14:53:33.281665+00	2026-08-30 14:53:33.281665+00	2026-08-30 14:53:33.281665+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T14:53:34.000Z", "contentLength": 107792, "httpStatusCode": 200}	cb47e9a5-4a3f-49b5-aa15-9e590019bca2	\N	{}	\N	f	f
b4d65796-2abd-48ac-ab1f-c135d57b5de1	documents	attachments/organization_renewal/89/mMbgTDqfB2b7HIVdEGkq4xHgOvivqTI6CTfVPYz1.pdf	\N	2026-08-30 14:53:33.417264+00	2026-08-30 14:53:33.417264+00	2026-08-30 14:53:33.417264+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-08-30T14:53:34.000Z", "contentLength": 107792, "httpStatusCode": 200}	6cc2dbd0-7ca3-429c-968d-80cd1c57ec88	\N	{}	\N	f	f
9ef2544e-8903-4f07-bd9d-f3d5d1fb8129	documents	attachments/organization_registration/90/Wu9lVGca7VPFXLbdd7UyOznGOL56CaFxx3hYIe0o.pdf	\N	2026-09-03 08:06:36.718421+00	2026-09-03 08:06:36.718421+00	2026-09-03 08:06:36.718421+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:06:37.000Z", "contentLength": 107792, "httpStatusCode": 200}	5b0dd9f5-5096-483c-9724-35ccc3aec906	\N	{}	\N	f	f
6a21fe67-967e-45d0-96ea-c4511e1d70f5	documents	attachments/organization_registration/90/qFBRUsTXdLL25vdSvhepjlxrvB0mwe9ZTAfAG4Hp.pdf	\N	2026-09-03 08:06:36.883763+00	2026-09-03 08:06:36.883763+00	2026-09-03 08:06:36.883763+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:06:37.000Z", "contentLength": 107792, "httpStatusCode": 200}	c393f9ea-2530-4439-bd4b-b9b4ada44a6a	\N	{}	\N	f	f
a6967a6f-5c9a-47aa-9a69-f8738d1717a8	documents	attachments/organization_registration/90/TqkiIilu1tXmvfRcfbXbvQjcvcIJGhZl34VZmTwt.pdf	\N	2026-09-03 08:06:37.28299+00	2026-09-03 08:06:37.28299+00	2026-09-03 08:06:37.28299+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:06:38.000Z", "contentLength": 107792, "httpStatusCode": 200}	4674529a-ad5a-46f9-96bf-c5c59bd9a111	\N	{}	\N	f	f
2db5d81c-5ae2-4713-a553-0329462d0fa5	documents	attachments/organization_registration/90/FUQjaSZ1LDJ7NLjRNYR6iOiIvF3ziYUymMQUizoQ.pdf	\N	2026-09-03 08:06:37.414991+00	2026-09-03 08:06:37.414991+00	2026-09-03 08:06:37.414991+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:06:38.000Z", "contentLength": 107792, "httpStatusCode": 200}	7860a3af-2d62-41e5-b5f8-5c3170b3c008	\N	{}	\N	f	f
6768a4a8-c8bd-4a30-9808-348f12204857	documents	attachments/organization_registration/90/BVwtwhBbmEvUsb0BkWXLmrU43GCXX104O6DsftNk.pdf	\N	2026-09-03 08:06:37.557085+00	2026-09-03 08:06:37.557085+00	2026-09-03 08:06:37.557085+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:06:38.000Z", "contentLength": 107792, "httpStatusCode": 200}	6640673a-65ed-4b21-b714-bc1a4256a322	\N	{}	\N	f	f
53951643-2bac-44dc-8196-1acd38890688	documents	attachments/organization_registration/90/TAY6hUFGbuYZZzaeDJvhQMvDBoRxzmoJERp4e3ag.pdf	\N	2026-09-03 08:06:37.750413+00	2026-09-03 08:06:37.750413+00	2026-09-03 08:06:37.750413+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:06:38.000Z", "contentLength": 107792, "httpStatusCode": 200}	288d0f91-c28c-461f-bcf8-193194a77c2e	\N	{}	\N	f	f
4c9eea1e-94fb-4db7-9b74-d654d048fee8	documents	attachments/organization_renewal/91/GLpG420uefOoWxCxNkEtICPF5yx8fplFIOcryqZK.pdf	\N	2026-09-03 08:08:01.658139+00	2026-09-03 08:08:01.658139+00	2026-09-03 08:08:01.658139+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:08:02.000Z", "contentLength": 107792, "httpStatusCode": 200}	fc0c7e7f-64ca-4bd2-b2b5-d80349490c3d	\N	{}	\N	f	f
cfa5abb8-d67f-4442-9eb1-34811182d7f4	documents	attachments/organization_renewal/91/4RXLt9MAPl3Dve0NZqQhEjff0tTh4WNGGXrwZQNc.pdf	\N	2026-09-03 08:08:01.797203+00	2026-09-03 08:08:01.797203+00	2026-09-03 08:08:01.797203+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:08:02.000Z", "contentLength": 107792, "httpStatusCode": 200}	dec21bf6-4174-4f4e-bf65-c754a3814fc3	\N	{}	\N	f	f
a687f3d5-6c94-4d5a-b0ca-196bd2343d79	documents	attachments/organization_renewal/91/UNg0EwxxQNf2ADTL14Lv8BkxtBQUyOUrwyTUMyVo.pdf	\N	2026-09-03 08:08:01.933378+00	2026-09-03 08:08:01.933378+00	2026-09-03 08:08:01.933378+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:08:02.000Z", "contentLength": 107792, "httpStatusCode": 200}	e49ca7d2-c387-4771-b383-b84a76d4f5d2	\N	{}	\N	f	f
61a459d2-b8a2-4b89-941f-a0b28e843e8c	documents	attachments/organization_renewal/91/KvEMPbW7CKxnL6A6MrdvBoKWeBiUEMObaOmvHX5q.pdf	\N	2026-09-03 08:08:02.070114+00	2026-09-03 08:08:02.070114+00	2026-09-03 08:08:02.070114+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:08:03.000Z", "contentLength": 107792, "httpStatusCode": 200}	720f49c4-ef46-4db7-8d3b-4f10e30bd61a	\N	{}	\N	f	f
fa0286e2-741a-4fb4-a8e6-7217c2489966	documents	attachments/organization_renewal/91/V5Jqnb6kD1zRrjPUGlEI3q1tsHeEH8XdY2nQ2q8v.pdf	\N	2026-09-03 08:08:02.212288+00	2026-09-03 08:08:02.212288+00	2026-09-03 08:08:02.212288+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:08:03.000Z", "contentLength": 107792, "httpStatusCode": 200}	043b316d-6365-45d5-af65-ffa76833d722	\N	{}	\N	f	f
c71d3169-45d2-4def-8a64-9d5bdd3bb65b	documents	attachments/organization_renewal/91/VbYwSSVIUrnywng3ZfxNS6zLTmpTNbTDdJUfqu9h.pdf	\N	2026-09-03 08:08:02.325411+00	2026-09-03 08:08:02.325411+00	2026-09-03 08:08:02.325411+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:08:03.000Z", "contentLength": 107792, "httpStatusCode": 200}	6701ab76-b8bb-4e4e-80dd-23cf028ca93d	\N	{}	\N	f	f
17e9762e-058e-4f20-b51e-1e9573c43bfb	documents	attachments/organization_renewal/91/0JbMxYe2ykG3zyN8SwbMZFgqfxVX1rRxwcSPbd2A.pdf	\N	2026-09-03 08:08:02.455826+00	2026-09-03 08:08:02.455826+00	2026-09-03 08:08:02.455826+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:08:03.000Z", "contentLength": 107792, "httpStatusCode": 200}	b87fbd79-0f40-4e69-913b-df5a617bc7f6	\N	{}	\N	f	f
f3da83a7-cf6c-408f-b299-fe6039116a4c	documents	attachments/organization_renewal/91/fiw7wP2WiqLkRclE154tJXh5xXz7DsqX22meV3dH.pdf	\N	2026-09-03 08:08:03.109877+00	2026-09-03 08:08:03.109877+00	2026-09-03 08:08:03.109877+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:08:04.000Z", "contentLength": 107792, "httpStatusCode": 200}	3ddb14a4-064a-4655-a98d-9bbf8d86ca3f	\N	{}	\N	f	f
d80f5065-7310-470c-a672-0e90baef3fb9	documents	attachments/organization_renewal/91/hrHcI9qaIoa35PYoDWc54xa7YO5B2S2xOL97YD9l.pdf	\N	2026-09-03 08:08:03.298335+00	2026-09-03 08:08:03.298335+00	2026-09-03 08:08:03.298335+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:08:04.000Z", "contentLength": 107792, "httpStatusCode": 200}	5010c0fe-c0fc-40ce-abfe-edace7b37d4f	\N	{}	\N	f	f
848532da-9ab5-4693-8e80-741e04cb9fd9	documents	attachments/organization_renewal/92/0J2weZYNcWb3QP7PRO9efjUtnc0kCNSur7bIVRwS.pdf	\N	2026-09-03 08:10:22.049699+00	2026-09-03 08:10:22.049699+00	2026-09-03 08:10:22.049699+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:10:23.000Z", "contentLength": 107792, "httpStatusCode": 200}	560027b8-d1bd-40cf-acd1-f9b46d6d556f	\N	{}	\N	f	f
ad52bee9-c931-48ad-b760-19c51bf88f1e	documents	attachments/organization_renewal/92/CGuni9MCCGvT9TxpGJqucNdYzPjHUx0Erl3YDW0I.pdf	\N	2026-09-03 08:10:22.186206+00	2026-09-03 08:10:22.186206+00	2026-09-03 08:10:22.186206+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:10:23.000Z", "contentLength": 107792, "httpStatusCode": 200}	352b02a7-530e-4b39-acf1-9855808f6dd9	\N	{}	\N	f	f
434e50d3-e6ba-46b8-9b72-ac1659eb02c6	documents	attachments/organization_renewal/92/CcMFkLlg4zM9b4gbcTxhggQ60H5Cm7pGen1LIBDB.pdf	\N	2026-09-03 08:10:22.307671+00	2026-09-03 08:10:22.307671+00	2026-09-03 08:10:22.307671+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:10:23.000Z", "contentLength": 107792, "httpStatusCode": 200}	aa4cb89e-1e1d-42fa-ad71-11567330f82f	\N	{}	\N	f	f
56ab2cda-9dff-4065-a85f-18aa9f1e11b0	documents	attachments/organization_renewal/92/legmxcZDLad6IL1URGVkIuDPAUy12woC9y8xp8Zx.pdf	\N	2026-09-03 08:10:22.453734+00	2026-09-03 08:10:22.453734+00	2026-09-03 08:10:22.453734+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:10:23.000Z", "contentLength": 107792, "httpStatusCode": 200}	fe78e2f1-4932-4c95-97d9-e8a56aaaae8e	\N	{}	\N	f	f
a0e1a76c-bd0b-4789-9cd4-ab16508251ac	documents	attachments/organization_renewal/92/7veg1lncYCYzA70jYJdQNomDNkaCnLA5GKn6vumN.pdf	\N	2026-09-03 08:10:22.57326+00	2026-09-03 08:10:22.57326+00	2026-09-03 08:10:22.57326+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:10:23.000Z", "contentLength": 107792, "httpStatusCode": 200}	23b18bc8-1996-4958-be22-33f54b9b3162	\N	{}	\N	f	f
1f0e86ff-6fee-45bf-a81b-757975509222	documents	attachments/organization_renewal/92/XiN634eniySm3aQxzeS7A0CZI7XulJM8U6oU7kFZ.pdf	\N	2026-09-03 08:10:22.730859+00	2026-09-03 08:10:22.730859+00	2026-09-03 08:10:22.730859+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:10:23.000Z", "contentLength": 107792, "httpStatusCode": 200}	f91f11e0-6c25-4337-966b-f7e1ad2867ee	\N	{}	\N	f	f
d74fdd0b-c669-4120-898b-c4d45aba5857	documents	attachments/organization_renewal/92/Gc1ZnqFefIyfXIJkgJBE1GoOcYdmxnLJjNIga0OV.pdf	\N	2026-09-03 08:10:22.890036+00	2026-09-03 08:10:22.890036+00	2026-09-03 08:10:22.890036+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:10:23.000Z", "contentLength": 107792, "httpStatusCode": 200}	6546d3d5-ed8b-4de9-ac24-94678fdcdabf	\N	{}	\N	f	f
5914eb78-f2ed-48c4-9f63-5477baeaa7b5	documents	attachments/organization_renewal/92/Z6SZ2ykT5wpE5nO5g4YdE45fgAOqNwfJEhv2Ct3Q.pdf	\N	2026-09-03 08:10:23.01453+00	2026-09-03 08:10:23.01453+00	2026-09-03 08:10:23.01453+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:10:24.000Z", "contentLength": 107792, "httpStatusCode": 200}	8aac88a9-27ab-4f42-b8cd-152ecd8b5635	\N	{}	\N	f	f
35a10c0a-d848-4cc0-ba86-d9269d1c3d31	documents	attachments/organization_renewal/92/7PyqXXiTrDNLnnWjsMkopF52zwMQT9T5dzkXBOlS.pdf	\N	2026-09-03 08:10:23.134755+00	2026-09-03 08:10:23.134755+00	2026-09-03 08:10:23.134755+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-03T08:10:24.000Z", "contentLength": 107792, "httpStatusCode": 200}	70eea90a-2386-4ca0-bb82-2ea322a215fa	\N	{}	\N	f	f
b5db9dc4-7451-40f4-8b04-fdbaf30f9a3e	documents	attachments/activity_proposal/102/ZEdo7wpHzPcGO2T0jONS2TChc5OsduAwIL0fyfjf.pdf	\N	2026-09-05 11:01:41.928689+00	2026-09-05 11:01:41.928689+00	2026-09-05 11:01:41.928689+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-05T11:01:42.000Z", "contentLength": 107792, "httpStatusCode": 200}	f327e30a-bc69-40e8-997a-121c1b404ed0	\N	{}	\N	f	f
2fe9f7ef-b3ee-4843-bb2c-077f8887aa9b	documents	attachments/organization_registration/104/FlrAdpOikTaaxtd2KSn42XPqIp6AWWeD9CCFfBrz.pdf	\N	2026-09-05 13:39:54.816099+00	2026-09-05 13:39:54.816099+00	2026-09-05 13:39:54.816099+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-05T13:39:55.000Z", "contentLength": 107792, "httpStatusCode": 200}	b2ea8def-b0a1-4783-987b-d268a168b668	\N	{}	\N	f	f
9d6ff714-452b-4cbc-b425-7f7e04dc612e	documents	attachments/organization_registration/104/tur31tmKRvVfsHhOMzGhJ0Ix3mvBy7OxMOO79QHQ.pdf	\N	2026-09-05 13:39:54.989535+00	2026-09-05 13:39:54.989535+00	2026-09-05 13:39:54.989535+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-05T13:39:55.000Z", "contentLength": 107792, "httpStatusCode": 200}	8b1e9bdf-6c5f-42a2-81b1-30cb3e73bf1e	\N	{}	\N	f	f
634fead1-ed19-40d2-87c1-5c13c4fd7b8f	documents	attachments/organization_registration/104/RoqEezctCESFxww3LwpgbyT2D5Tn93MuN9kaaFeC.pdf	\N	2026-09-05 13:39:55.13484+00	2026-09-05 13:39:55.13484+00	2026-09-05 13:39:55.13484+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-05T13:39:56.000Z", "contentLength": 107792, "httpStatusCode": 200}	4699cd0b-5499-4980-903a-fdabbfd06e17	\N	{}	\N	f	f
6f41b9af-eaef-437b-9518-536aac377758	documents	attachments/organization_registration/104/uyk7gpzk0y9SeRN2ofDrkm76SvvTfo5uhdN3ljop.pdf	\N	2026-09-05 13:39:55.293245+00	2026-09-05 13:39:55.293245+00	2026-09-05 13:39:55.293245+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-05T13:39:56.000Z", "contentLength": 107792, "httpStatusCode": 200}	975efaad-ddb4-4202-aba4-02c5b712a632	\N	{}	\N	f	f
227c4c2b-ed71-40d8-ab55-a3b0c3ef3260	documents	attachments/organization_registration/104/pHrJtWBhOVifwnpxCMyEV24avPHdqJAdH7MIpFy0.pdf	\N	2026-09-05 13:39:55.448583+00	2026-09-05 13:39:55.448583+00	2026-09-05 13:39:55.448583+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-05T13:39:56.000Z", "contentLength": 107792, "httpStatusCode": 200}	5dc82708-a69f-4187-954a-b5ba99d5c73a	\N	{}	\N	f	f
aab04859-7dea-4e65-b3a4-cf676aa3fcd4	documents	attachments/organization_registration/104/7aKFpJUlJezQ2MYgnZV0t7VYOsbLs73xrwjljnxv.pdf	\N	2026-09-05 13:39:55.581619+00	2026-09-05 13:39:55.581619+00	2026-09-05 13:39:55.581619+00	{"eTag": "\\"8cafc908b8d8bbbd80855492a631981b\\"", "size": 107792, "mimetype": "application/pdf", "cacheControl": "no-cache", "lastModified": "2026-09-05T13:39:56.000Z", "contentLength": 107792, "httpStatusCode": 200}	995140af-6ee8-4524-b3ad-9e080d8666c8	\N	{}	\N	f	f
\.


--
-- Data for Name: s3_multipart_uploads; Type: TABLE DATA; Schema: storage; Owner: supabase_storage_admin
--

COPY storage.s3_multipart_uploads (id, in_progress_size, upload_signature, bucket_id, key, version, owner_id, created_at, user_metadata, metadata) FROM stdin;
\.


--
-- Data for Name: s3_multipart_uploads_parts; Type: TABLE DATA; Schema: storage; Owner: supabase_storage_admin
--

COPY storage.s3_multipart_uploads_parts (id, upload_id, size, part_number, bucket_id, key, etag, owner_id, version, created_at) FROM stdin;
\.


--
-- Data for Name: vector_indexes; Type: TABLE DATA; Schema: storage; Owner: supabase_storage_admin
--

COPY storage.vector_indexes (id, name, bucket_id, data_type, dimension, distance_metric, metadata_configuration, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: secrets; Type: TABLE DATA; Schema: vault; Owner: supabase_admin
--

COPY vault.secrets (id, name, description, secret, key_id, nonce, created_at, updated_at) FROM stdin;
\.


--
-- Name: refresh_tokens_id_seq; Type: SEQUENCE SET; Schema: auth; Owner: supabase_auth_admin
--

SELECT pg_catalog.setval('auth.refresh_tokens_id_seq', 1, false);


--
-- Name: activity_calendars_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.activity_calendars_id_seq', 35, true);


--
-- Name: activity_proposals_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.activity_proposals_id_seq', 20, true);


--
-- Name: after_activity_reports_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.after_activity_reports_id_seq', 6, true);


--
-- Name: approval_notifications_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.approval_notifications_id_seq', 487, true);


--
-- Name: calendar_activities_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.calendar_activities_id_seq', 60, true);


--
-- Name: document_attachments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.document_attachments_id_seq', 350, true);


--
-- Name: document_step_approvals_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.document_step_approvals_id_seq', 182, true);


--
-- Name: document_transitions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.document_transitions_id_seq', 440, true);


--
-- Name: documents_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.documents_id_seq', 104, true);


--
-- Name: email_verification_codes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.email_verification_codes_id_seq', 60, true);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 23, true);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.jobs_id_seq', 732, true);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.migrations_id_seq', 48, true);


--
-- Name: organization_join_requests_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.organization_join_requests_id_seq', 5, true);


--
-- Name: organization_memberships_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.organization_memberships_id_seq', 33, true);


--
-- Name: organization_registration_details_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.organization_registration_details_id_seq', 50, true);


--
-- Name: organizations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.organizations_id_seq', 43, true);


--
-- Name: passkeys_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.passkeys_id_seq', 1, false);


--
-- Name: programs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.programs_id_seq', 24, true);


--
-- Name: role_assignments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.role_assignments_id_seq', 76, true);


--
-- Name: schools_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.schools_id_seq', 12, true);


--
-- Name: settings_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.settings_id_seq', 2, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.users_id_seq', 142, true);


--
-- Name: workflow_steps_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.workflow_steps_id_seq', 34, true);


--
-- Name: workflow_templates_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.workflow_templates_id_seq', 13, true);


--
-- Name: subscription_id_seq; Type: SEQUENCE SET; Schema: realtime; Owner: supabase_realtime_admin
--

SELECT pg_catalog.setval('realtime.subscription_id_seq', 1, false);


--
-- Name: mfa_amr_claims amr_id_pk; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.mfa_amr_claims
    ADD CONSTRAINT amr_id_pk PRIMARY KEY (id);


--
-- Name: audit_log_entries audit_log_entries_pkey; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.audit_log_entries
    ADD CONSTRAINT audit_log_entries_pkey PRIMARY KEY (id);


--
-- Name: custom_oauth_providers custom_oauth_providers_identifier_key; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.custom_oauth_providers
    ADD CONSTRAINT custom_oauth_providers_identifier_key UNIQUE (identifier);


--
-- Name: custom_oauth_providers custom_oauth_providers_pkey; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.custom_oauth_providers
    ADD CONSTRAINT custom_oauth_providers_pkey PRIMARY KEY (id);


--
-- Name: flow_state flow_state_pkey; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.flow_state
    ADD CONSTRAINT flow_state_pkey PRIMARY KEY (id);


--
-- Name: identities identities_pkey; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.identities
    ADD CONSTRAINT identities_pkey PRIMARY KEY (id);


--
-- Name: identities identities_provider_id_provider_unique; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.identities
    ADD CONSTRAINT identities_provider_id_provider_unique UNIQUE (provider_id, provider);


--
-- Name: instances instances_pkey; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.instances
    ADD CONSTRAINT instances_pkey PRIMARY KEY (id);


--
-- Name: mfa_amr_claims mfa_amr_claims_session_id_authentication_method_pkey; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.mfa_amr_claims
    ADD CONSTRAINT mfa_amr_claims_session_id_authentication_method_pkey UNIQUE (session_id, authentication_method);


--
-- Name: mfa_challenges mfa_challenges_pkey; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.mfa_challenges
    ADD CONSTRAINT mfa_challenges_pkey PRIMARY KEY (id);


--
-- Name: mfa_factors mfa_factors_last_challenged_at_key; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.mfa_factors
    ADD CONSTRAINT mfa_factors_last_challenged_at_key UNIQUE (last_challenged_at);


--
-- Name: mfa_factors mfa_factors_pkey; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.mfa_factors
    ADD CONSTRAINT mfa_factors_pkey PRIMARY KEY (id);


--
-- Name: oauth_authorizations oauth_authorizations_authorization_code_key; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.oauth_authorizations
    ADD CONSTRAINT oauth_authorizations_authorization_code_key UNIQUE (authorization_code);


--
-- Name: oauth_authorizations oauth_authorizations_authorization_id_key; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.oauth_authorizations
    ADD CONSTRAINT oauth_authorizations_authorization_id_key UNIQUE (authorization_id);


--
-- Name: oauth_authorizations oauth_authorizations_pkey; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.oauth_authorizations
    ADD CONSTRAINT oauth_authorizations_pkey PRIMARY KEY (id);


--
-- Name: oauth_client_states oauth_client_states_pkey; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.oauth_client_states
    ADD CONSTRAINT oauth_client_states_pkey PRIMARY KEY (id);


--
-- Name: oauth_clients oauth_clients_pkey; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.oauth_clients
    ADD CONSTRAINT oauth_clients_pkey PRIMARY KEY (id);


--
-- Name: oauth_consents oauth_consents_pkey; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.oauth_consents
    ADD CONSTRAINT oauth_consents_pkey PRIMARY KEY (id);


--
-- Name: oauth_consents oauth_consents_user_client_unique; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.oauth_consents
    ADD CONSTRAINT oauth_consents_user_client_unique UNIQUE (user_id, client_id);


--
-- Name: one_time_tokens one_time_tokens_pkey; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.one_time_tokens
    ADD CONSTRAINT one_time_tokens_pkey PRIMARY KEY (id);


--
-- Name: refresh_tokens refresh_tokens_pkey; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.refresh_tokens
    ADD CONSTRAINT refresh_tokens_pkey PRIMARY KEY (id);


--
-- Name: refresh_tokens refresh_tokens_token_unique; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.refresh_tokens
    ADD CONSTRAINT refresh_tokens_token_unique UNIQUE (token);


--
-- Name: saml_providers saml_providers_entity_id_key; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.saml_providers
    ADD CONSTRAINT saml_providers_entity_id_key UNIQUE (entity_id);


--
-- Name: saml_providers saml_providers_pkey; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.saml_providers
    ADD CONSTRAINT saml_providers_pkey PRIMARY KEY (id);


--
-- Name: saml_relay_states saml_relay_states_pkey; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.saml_relay_states
    ADD CONSTRAINT saml_relay_states_pkey PRIMARY KEY (id);


--
-- Name: schema_migrations schema_migrations_pkey; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.schema_migrations
    ADD CONSTRAINT schema_migrations_pkey PRIMARY KEY (version);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: sso_domains sso_domains_pkey; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.sso_domains
    ADD CONSTRAINT sso_domains_pkey PRIMARY KEY (id);


--
-- Name: sso_providers sso_providers_pkey; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.sso_providers
    ADD CONSTRAINT sso_providers_pkey PRIMARY KEY (id);


--
-- Name: users users_phone_key; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.users
    ADD CONSTRAINT users_phone_key UNIQUE (phone);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: webauthn_challenges webauthn_challenges_pkey; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.webauthn_challenges
    ADD CONSTRAINT webauthn_challenges_pkey PRIMARY KEY (id);


--
-- Name: webauthn_credentials webauthn_credentials_pkey; Type: CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.webauthn_credentials
    ADD CONSTRAINT webauthn_credentials_pkey PRIMARY KEY (id);


--
-- Name: activity_calendars activity_calendars_document_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.activity_calendars
    ADD CONSTRAINT activity_calendars_document_id_unique UNIQUE (document_id);


--
-- Name: activity_calendars activity_calendars_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.activity_calendars
    ADD CONSTRAINT activity_calendars_pkey PRIMARY KEY (id);


--
-- Name: activity_proposals activity_proposals_document_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.activity_proposals
    ADD CONSTRAINT activity_proposals_document_id_unique UNIQUE (document_id);


--
-- Name: activity_proposals activity_proposals_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.activity_proposals
    ADD CONSTRAINT activity_proposals_pkey PRIMARY KEY (id);


--
-- Name: after_activity_reports after_activity_reports_document_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.after_activity_reports
    ADD CONSTRAINT after_activity_reports_document_id_unique UNIQUE (document_id);


--
-- Name: after_activity_reports after_activity_reports_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.after_activity_reports
    ADD CONSTRAINT after_activity_reports_pkey PRIMARY KEY (id);


--
-- Name: approval_notifications approval_notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.approval_notifications
    ADD CONSTRAINT approval_notifications_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: calendar_activities calendar_activities_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.calendar_activities
    ADD CONSTRAINT calendar_activities_pkey PRIMARY KEY (id);


--
-- Name: document_attachments document_attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.document_attachments
    ADD CONSTRAINT document_attachments_pkey PRIMARY KEY (id);


--
-- Name: document_step_approvals document_step_approvals_document_id_workflow_step_id_user_id_un; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.document_step_approvals
    ADD CONSTRAINT document_step_approvals_document_id_workflow_step_id_user_id_un UNIQUE (document_id, workflow_step_id, user_id);


--
-- Name: document_step_approvals document_step_approvals_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.document_step_approvals
    ADD CONSTRAINT document_step_approvals_pkey PRIMARY KEY (id);


--
-- Name: document_transitions document_transitions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.document_transitions
    ADD CONSTRAINT document_transitions_pkey PRIMARY KEY (id);


--
-- Name: documents documents_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_pkey PRIMARY KEY (id);


--
-- Name: email_verification_codes email_verification_codes_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.email_verification_codes
    ADD CONSTRAINT email_verification_codes_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- Name: organization_join_requests organization_join_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.organization_join_requests
    ADD CONSTRAINT organization_join_requests_pkey PRIMARY KEY (id);


--
-- Name: organization_memberships organization_memberships_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.organization_memberships
    ADD CONSTRAINT organization_memberships_pkey PRIMARY KEY (id);


--
-- Name: organization_registration_details organization_registration_details_document_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.organization_registration_details
    ADD CONSTRAINT organization_registration_details_document_id_unique UNIQUE (document_id);


--
-- Name: organization_registration_details organization_registration_details_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.organization_registration_details
    ADD CONSTRAINT organization_registration_details_pkey PRIMARY KEY (id);


--
-- Name: organizations organizations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.organizations
    ADD CONSTRAINT organizations_pkey PRIMARY KEY (id);


--
-- Name: passkeys passkeys_credential_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.passkeys
    ADD CONSTRAINT passkeys_credential_id_unique UNIQUE (credential_id);


--
-- Name: passkeys passkeys_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.passkeys
    ADD CONSTRAINT passkeys_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: programs programs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.programs
    ADD CONSTRAINT programs_pkey PRIMARY KEY (id);


--
-- Name: role_assignments role_assignments_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.role_assignments
    ADD CONSTRAINT role_assignments_pkey PRIMARY KEY (id);


--
-- Name: schools schools_name_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.schools
    ADD CONSTRAINT schools_name_unique UNIQUE (name);


--
-- Name: schools schools_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.schools
    ADD CONSTRAINT schools_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: settings settings_key_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.settings
    ADD CONSTRAINT settings_key_unique UNIQUE (key);


--
-- Name: settings settings_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.settings
    ADD CONSTRAINT settings_pkey PRIMARY KEY (id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_id_number_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_id_number_unique UNIQUE (id_number);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: workflow_steps workflow_steps_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.workflow_steps
    ADD CONSTRAINT workflow_steps_pkey PRIMARY KEY (id);


--
-- Name: workflow_steps workflow_steps_workflow_template_id_position_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.workflow_steps
    ADD CONSTRAINT workflow_steps_workflow_template_id_position_unique UNIQUE (workflow_template_id, "position");


--
-- Name: workflow_templates workflow_templates_form_type_variant_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.workflow_templates
    ADD CONSTRAINT workflow_templates_form_type_variant_unique UNIQUE (form_type, variant);


--
-- Name: workflow_templates workflow_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.workflow_templates
    ADD CONSTRAINT workflow_templates_pkey PRIMARY KEY (id);


--
-- Name: messages messages_payload_exclusive; Type: CHECK CONSTRAINT; Schema: realtime; Owner: supabase_realtime_admin
--

ALTER TABLE realtime.messages
    ADD CONSTRAINT messages_payload_exclusive CHECK (((payload IS NULL) OR (binary_payload IS NULL))) NOT VALID;


--
-- Name: messages messages_pkey; Type: CONSTRAINT; Schema: realtime; Owner: supabase_realtime_admin
--

ALTER TABLE ONLY realtime.messages
    ADD CONSTRAINT messages_pkey PRIMARY KEY (id, inserted_at);


--
-- Name: subscription pk_subscription; Type: CONSTRAINT; Schema: realtime; Owner: supabase_realtime_admin
--

ALTER TABLE ONLY realtime.subscription
    ADD CONSTRAINT pk_subscription PRIMARY KEY (id);


--
-- Name: schema_migrations schema_migrations_pkey; Type: CONSTRAINT; Schema: realtime; Owner: supabase_admin
--

ALTER TABLE ONLY realtime.schema_migrations
    ADD CONSTRAINT schema_migrations_pkey PRIMARY KEY (version);


--
-- Name: buckets_analytics buckets_analytics_pkey; Type: CONSTRAINT; Schema: storage; Owner: supabase_storage_admin
--

ALTER TABLE ONLY storage.buckets_analytics
    ADD CONSTRAINT buckets_analytics_pkey PRIMARY KEY (id);


--
-- Name: buckets buckets_pkey; Type: CONSTRAINT; Schema: storage; Owner: supabase_storage_admin
--

ALTER TABLE ONLY storage.buckets
    ADD CONSTRAINT buckets_pkey PRIMARY KEY (id);


--
-- Name: buckets_vectors buckets_vectors_pkey; Type: CONSTRAINT; Schema: storage; Owner: supabase_storage_admin
--

ALTER TABLE ONLY storage.buckets_vectors
    ADD CONSTRAINT buckets_vectors_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_name_key; Type: CONSTRAINT; Schema: storage; Owner: supabase_storage_admin
--

ALTER TABLE ONLY storage.migrations
    ADD CONSTRAINT migrations_name_key UNIQUE (name);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: storage; Owner: supabase_storage_admin
--

ALTER TABLE ONLY storage.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: objects objects_pkey; Type: CONSTRAINT; Schema: storage; Owner: supabase_storage_admin
--

ALTER TABLE ONLY storage.objects
    ADD CONSTRAINT objects_pkey PRIMARY KEY (id);


--
-- Name: s3_multipart_uploads_parts s3_multipart_uploads_parts_pkey; Type: CONSTRAINT; Schema: storage; Owner: supabase_storage_admin
--

ALTER TABLE ONLY storage.s3_multipart_uploads_parts
    ADD CONSTRAINT s3_multipart_uploads_parts_pkey PRIMARY KEY (id);


--
-- Name: s3_multipart_uploads s3_multipart_uploads_pkey; Type: CONSTRAINT; Schema: storage; Owner: supabase_storage_admin
--

ALTER TABLE ONLY storage.s3_multipart_uploads
    ADD CONSTRAINT s3_multipart_uploads_pkey PRIMARY KEY (id);


--
-- Name: vector_indexes vector_indexes_pkey; Type: CONSTRAINT; Schema: storage; Owner: supabase_storage_admin
--

ALTER TABLE ONLY storage.vector_indexes
    ADD CONSTRAINT vector_indexes_pkey PRIMARY KEY (id);


--
-- Name: audit_logs_instance_id_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX audit_logs_instance_id_idx ON auth.audit_log_entries USING btree (instance_id);


--
-- Name: confirmation_token_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE UNIQUE INDEX confirmation_token_idx ON auth.users USING btree (confirmation_token) WHERE ((confirmation_token)::text !~ '^[0-9 ]*$'::text);


--
-- Name: custom_oauth_providers_created_at_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX custom_oauth_providers_created_at_idx ON auth.custom_oauth_providers USING btree (created_at);


--
-- Name: custom_oauth_providers_enabled_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX custom_oauth_providers_enabled_idx ON auth.custom_oauth_providers USING btree (enabled);


--
-- Name: custom_oauth_providers_identifier_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX custom_oauth_providers_identifier_idx ON auth.custom_oauth_providers USING btree (identifier);


--
-- Name: custom_oauth_providers_provider_type_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX custom_oauth_providers_provider_type_idx ON auth.custom_oauth_providers USING btree (provider_type);


--
-- Name: email_change_token_current_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE UNIQUE INDEX email_change_token_current_idx ON auth.users USING btree (email_change_token_current) WHERE ((email_change_token_current)::text !~ '^[0-9 ]*$'::text);


--
-- Name: email_change_token_new_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE UNIQUE INDEX email_change_token_new_idx ON auth.users USING btree (email_change_token_new) WHERE ((email_change_token_new)::text !~ '^[0-9 ]*$'::text);


--
-- Name: factor_id_created_at_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX factor_id_created_at_idx ON auth.mfa_factors USING btree (user_id, created_at);


--
-- Name: flow_state_created_at_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX flow_state_created_at_idx ON auth.flow_state USING btree (created_at DESC);


--
-- Name: identities_email_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX identities_email_idx ON auth.identities USING btree (email text_pattern_ops);


--
-- Name: INDEX identities_email_idx; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON INDEX auth.identities_email_idx IS 'Auth: Ensures indexed queries on the email column';


--
-- Name: identities_user_id_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX identities_user_id_idx ON auth.identities USING btree (user_id);


--
-- Name: idx_auth_code; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX idx_auth_code ON auth.flow_state USING btree (auth_code);


--
-- Name: idx_oauth_client_states_created_at; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX idx_oauth_client_states_created_at ON auth.oauth_client_states USING btree (created_at);


--
-- Name: idx_user_id_auth_method; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX idx_user_id_auth_method ON auth.flow_state USING btree (user_id, authentication_method);


--
-- Name: idx_users_created_at_desc; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX idx_users_created_at_desc ON auth.users USING btree (created_at DESC);


--
-- Name: idx_users_email; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX idx_users_email ON auth.users USING btree (email);


--
-- Name: idx_users_last_sign_in_at_desc; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX idx_users_last_sign_in_at_desc ON auth.users USING btree (last_sign_in_at DESC);


--
-- Name: idx_users_name; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX idx_users_name ON auth.users USING btree (((raw_user_meta_data ->> 'name'::text))) WHERE ((raw_user_meta_data ->> 'name'::text) IS NOT NULL);


--
-- Name: mfa_challenge_created_at_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX mfa_challenge_created_at_idx ON auth.mfa_challenges USING btree (created_at DESC);


--
-- Name: mfa_factors_user_friendly_name_unique; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE UNIQUE INDEX mfa_factors_user_friendly_name_unique ON auth.mfa_factors USING btree (friendly_name, user_id) WHERE (TRIM(BOTH FROM friendly_name) <> ''::text);


--
-- Name: mfa_factors_user_id_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX mfa_factors_user_id_idx ON auth.mfa_factors USING btree (user_id);


--
-- Name: oauth_auth_pending_exp_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX oauth_auth_pending_exp_idx ON auth.oauth_authorizations USING btree (expires_at) WHERE (status = 'pending'::auth.oauth_authorization_status);


--
-- Name: oauth_clients_deleted_at_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX oauth_clients_deleted_at_idx ON auth.oauth_clients USING btree (deleted_at);


--
-- Name: oauth_consents_active_client_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX oauth_consents_active_client_idx ON auth.oauth_consents USING btree (client_id) WHERE (revoked_at IS NULL);


--
-- Name: oauth_consents_active_user_client_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX oauth_consents_active_user_client_idx ON auth.oauth_consents USING btree (user_id, client_id) WHERE (revoked_at IS NULL);


--
-- Name: oauth_consents_user_order_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX oauth_consents_user_order_idx ON auth.oauth_consents USING btree (user_id, granted_at DESC);


--
-- Name: one_time_tokens_relates_to_hash_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX one_time_tokens_relates_to_hash_idx ON auth.one_time_tokens USING hash (relates_to);


--
-- Name: one_time_tokens_token_hash_hash_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX one_time_tokens_token_hash_hash_idx ON auth.one_time_tokens USING hash (token_hash);


--
-- Name: one_time_tokens_user_id_token_type_key; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE UNIQUE INDEX one_time_tokens_user_id_token_type_key ON auth.one_time_tokens USING btree (user_id, token_type);


--
-- Name: reauthentication_token_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE UNIQUE INDEX reauthentication_token_idx ON auth.users USING btree (reauthentication_token) WHERE ((reauthentication_token)::text !~ '^[0-9 ]*$'::text);


--
-- Name: recovery_token_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE UNIQUE INDEX recovery_token_idx ON auth.users USING btree (recovery_token) WHERE ((recovery_token)::text !~ '^[0-9 ]*$'::text);


--
-- Name: refresh_tokens_instance_id_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX refresh_tokens_instance_id_idx ON auth.refresh_tokens USING btree (instance_id);


--
-- Name: refresh_tokens_instance_id_user_id_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX refresh_tokens_instance_id_user_id_idx ON auth.refresh_tokens USING btree (instance_id, user_id);


--
-- Name: refresh_tokens_parent_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX refresh_tokens_parent_idx ON auth.refresh_tokens USING btree (parent);


--
-- Name: refresh_tokens_session_id_revoked_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX refresh_tokens_session_id_revoked_idx ON auth.refresh_tokens USING btree (session_id, revoked);


--
-- Name: refresh_tokens_updated_at_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX refresh_tokens_updated_at_idx ON auth.refresh_tokens USING btree (updated_at DESC);


--
-- Name: saml_providers_sso_provider_id_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX saml_providers_sso_provider_id_idx ON auth.saml_providers USING btree (sso_provider_id);


--
-- Name: saml_relay_states_created_at_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX saml_relay_states_created_at_idx ON auth.saml_relay_states USING btree (created_at DESC);


--
-- Name: saml_relay_states_for_email_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX saml_relay_states_for_email_idx ON auth.saml_relay_states USING btree (for_email);


--
-- Name: saml_relay_states_sso_provider_id_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX saml_relay_states_sso_provider_id_idx ON auth.saml_relay_states USING btree (sso_provider_id);


--
-- Name: sessions_not_after_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX sessions_not_after_idx ON auth.sessions USING btree (not_after DESC);


--
-- Name: sessions_oauth_client_id_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX sessions_oauth_client_id_idx ON auth.sessions USING btree (oauth_client_id);


--
-- Name: sessions_user_id_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX sessions_user_id_idx ON auth.sessions USING btree (user_id);


--
-- Name: sso_domains_domain_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE UNIQUE INDEX sso_domains_domain_idx ON auth.sso_domains USING btree (lower(domain));


--
-- Name: sso_domains_sso_provider_id_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX sso_domains_sso_provider_id_idx ON auth.sso_domains USING btree (sso_provider_id);


--
-- Name: sso_providers_resource_id_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE UNIQUE INDEX sso_providers_resource_id_idx ON auth.sso_providers USING btree (lower(resource_id));


--
-- Name: sso_providers_resource_id_pattern_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX sso_providers_resource_id_pattern_idx ON auth.sso_providers USING btree (resource_id text_pattern_ops);


--
-- Name: unique_phone_factor_per_user; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE UNIQUE INDEX unique_phone_factor_per_user ON auth.mfa_factors USING btree (user_id, phone);


--
-- Name: user_id_created_at_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX user_id_created_at_idx ON auth.sessions USING btree (user_id, created_at);


--
-- Name: users_email_partial_key; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE UNIQUE INDEX users_email_partial_key ON auth.users USING btree (email) WHERE (is_sso_user = false);


--
-- Name: INDEX users_email_partial_key; Type: COMMENT; Schema: auth; Owner: supabase_auth_admin
--

COMMENT ON INDEX auth.users_email_partial_key IS 'Auth: A partial unique index that applies only when is_sso_user is false';


--
-- Name: users_instance_id_email_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX users_instance_id_email_idx ON auth.users USING btree (instance_id, lower((email)::text));


--
-- Name: users_instance_id_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX users_instance_id_idx ON auth.users USING btree (instance_id);


--
-- Name: users_is_anonymous_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX users_is_anonymous_idx ON auth.users USING btree (is_anonymous);


--
-- Name: webauthn_challenges_expires_at_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX webauthn_challenges_expires_at_idx ON auth.webauthn_challenges USING btree (expires_at);


--
-- Name: webauthn_challenges_user_id_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX webauthn_challenges_user_id_idx ON auth.webauthn_challenges USING btree (user_id);


--
-- Name: webauthn_credentials_credential_id_key; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE UNIQUE INDEX webauthn_credentials_credential_id_key ON auth.webauthn_credentials USING btree (credential_id);


--
-- Name: webauthn_credentials_user_id_idx; Type: INDEX; Schema: auth; Owner: supabase_auth_admin
--

CREATE INDEX webauthn_credentials_user_id_idx ON auth.webauthn_credentials USING btree (user_id);


--
-- Name: approval_notifications_document_id_step_position_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX approval_notifications_document_id_step_position_index ON public.approval_notifications USING btree (document_id, step_position);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: calendar_activities_venue_activity_date_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX calendar_activities_venue_activity_date_index ON public.calendar_activities USING btree (venue, activity_date);


--
-- Name: document_attachments_document_id_slot_key_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX document_attachments_document_id_slot_key_index ON public.document_attachments USING btree (document_id, slot_key);


--
-- Name: document_step_approvals_document_id_step_position_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX document_step_approvals_document_id_step_position_index ON public.document_step_approvals USING btree (document_id, step_position);


--
-- Name: document_transitions_document_id_created_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX document_transitions_document_id_created_at_index ON public.document_transitions USING btree (document_id, created_at);


--
-- Name: documents_organization_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX documents_organization_id_index ON public.documents USING btree (organization_id);


--
-- Name: documents_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX documents_status_index ON public.documents USING btree (status);


--
-- Name: documents_workflow_template_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX documents_workflow_template_id_index ON public.documents USING btree (workflow_template_id);


--
-- Name: email_verification_codes_email_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX email_verification_codes_email_index ON public.email_verification_codes USING btree (email);


--
-- Name: failed_jobs_connection_queue_failed_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX failed_jobs_connection_queue_failed_at_index ON public.failed_jobs USING btree (connection, queue, failed_at);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: notifications_notifiable_type_notifiable_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX notifications_notifiable_type_notifiable_id_index ON public.notifications USING btree (notifiable_type, notifiable_id);


--
-- Name: organization_join_requests_organization_id_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX organization_join_requests_organization_id_status_index ON public.organization_join_requests USING btree (organization_id, status);


--
-- Name: organization_join_requests_user_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX organization_join_requests_user_id_index ON public.organization_join_requests USING btree (user_id);


--
-- Name: organization_memberships_organization_id_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX organization_memberships_organization_id_is_active_index ON public.organization_memberships USING btree (organization_id, is_active);


--
-- Name: organization_memberships_user_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX organization_memberships_user_id_index ON public.organization_memberships USING btree (user_id);


--
-- Name: organization_registration_details_academic_year_term_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX organization_registration_details_academic_year_term_index ON public.organization_registration_details USING btree (academic_year, term);


--
-- Name: organization_registration_details_covers_academic_year_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX organization_registration_details_covers_academic_year_index ON public.organization_registration_details USING btree (covers_academic_year);


--
-- Name: passkeys_user_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX passkeys_user_id_index ON public.passkeys USING btree (user_id);


--
-- Name: role_assignments_role_organization_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX role_assignments_role_organization_id_index ON public.role_assignments USING btree (role, organization_id);


--
-- Name: role_assignments_role_program_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX role_assignments_role_program_id_index ON public.role_assignments USING btree (role, program_id);


--
-- Name: role_assignments_role_school_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX role_assignments_role_school_id_index ON public.role_assignments USING btree (role, school_id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: users_account_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX users_account_status_index ON public.users USING btree (account_status);


--
-- Name: workflow_steps_workflow_template_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX workflow_steps_workflow_template_id_index ON public.workflow_steps USING btree (workflow_template_id);


--
-- Name: ix_realtime_subscription_entity; Type: INDEX; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE INDEX ix_realtime_subscription_entity ON realtime.subscription USING btree (entity);


--
-- Name: messages_inserted_at_topic_index; Type: INDEX; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE INDEX messages_inserted_at_topic_index ON ONLY realtime.messages USING btree (inserted_at DESC, topic) WHERE ((extension = 'broadcast'::text) AND (private IS TRUE));


--
-- Name: subscription_subscription_id_entity_filters_action_filter_selec; Type: INDEX; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE UNIQUE INDEX subscription_subscription_id_entity_filters_action_filter_selec ON realtime.subscription USING btree (subscription_id, entity, filters, action_filter, COALESCE(selected_columns, '{}'::text[]));


--
-- Name: bname; Type: INDEX; Schema: storage; Owner: supabase_storage_admin
--

CREATE UNIQUE INDEX bname ON storage.buckets USING btree (name);


--
-- Name: bucketid_objname; Type: INDEX; Schema: storage; Owner: supabase_storage_admin
--

CREATE UNIQUE INDEX bucketid_objname ON storage.objects USING btree (bucket_id, name);


--
-- Name: buckets_analytics_unique_name_idx; Type: INDEX; Schema: storage; Owner: supabase_storage_admin
--

CREATE UNIQUE INDEX buckets_analytics_unique_name_idx ON storage.buckets_analytics USING btree (name) WHERE (deleted_at IS NULL);


--
-- Name: idx_multipart_uploads_list; Type: INDEX; Schema: storage; Owner: supabase_storage_admin
--

CREATE INDEX idx_multipart_uploads_list ON storage.s3_multipart_uploads USING btree (bucket_id, key, created_at);


--
-- Name: idx_objects_bucket_id_name; Type: INDEX; Schema: storage; Owner: supabase_storage_admin
--

CREATE INDEX idx_objects_bucket_id_name ON storage.objects USING btree (bucket_id, name COLLATE "C");


--
-- Name: idx_objects_bucket_id_name_lower; Type: INDEX; Schema: storage; Owner: supabase_storage_admin
--

CREATE INDEX idx_objects_bucket_id_name_lower ON storage.objects USING btree (bucket_id, lower(name) COLLATE "C");


--
-- Name: name_prefix_search; Type: INDEX; Schema: storage; Owner: supabase_storage_admin
--

CREATE INDEX name_prefix_search ON storage.objects USING btree (name text_pattern_ops);


--
-- Name: vector_indexes_name_bucket_id_idx; Type: INDEX; Schema: storage; Owner: supabase_storage_admin
--

CREATE UNIQUE INDEX vector_indexes_name_bucket_id_idx ON storage.vector_indexes USING btree (name, bucket_id);


--
-- Name: subscription tr_check_filters; Type: TRIGGER; Schema: realtime; Owner: supabase_realtime_admin
--

CREATE TRIGGER tr_check_filters BEFORE INSERT OR UPDATE ON realtime.subscription FOR EACH ROW EXECUTE FUNCTION realtime.subscription_check_filters();


--
-- Name: buckets enforce_bucket_name_length_trigger; Type: TRIGGER; Schema: storage; Owner: supabase_storage_admin
--

CREATE TRIGGER enforce_bucket_name_length_trigger BEFORE INSERT OR UPDATE OF name ON storage.buckets FOR EACH ROW EXECUTE FUNCTION storage.enforce_bucket_name_length();


--
-- Name: buckets protect_buckets_delete; Type: TRIGGER; Schema: storage; Owner: supabase_storage_admin
--

CREATE TRIGGER protect_buckets_delete BEFORE DELETE ON storage.buckets FOR EACH STATEMENT EXECUTE FUNCTION storage.protect_delete();


--
-- Name: objects protect_objects_delete; Type: TRIGGER; Schema: storage; Owner: supabase_storage_admin
--

CREATE TRIGGER protect_objects_delete BEFORE DELETE ON storage.objects FOR EACH STATEMENT EXECUTE FUNCTION storage.protect_delete();


--
-- Name: objects update_objects_updated_at; Type: TRIGGER; Schema: storage; Owner: supabase_storage_admin
--

CREATE TRIGGER update_objects_updated_at BEFORE UPDATE ON storage.objects FOR EACH ROW EXECUTE FUNCTION storage.update_updated_at_column();


--
-- Name: identities identities_user_id_fkey; Type: FK CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.identities
    ADD CONSTRAINT identities_user_id_fkey FOREIGN KEY (user_id) REFERENCES auth.users(id) ON DELETE CASCADE;


--
-- Name: mfa_amr_claims mfa_amr_claims_session_id_fkey; Type: FK CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.mfa_amr_claims
    ADD CONSTRAINT mfa_amr_claims_session_id_fkey FOREIGN KEY (session_id) REFERENCES auth.sessions(id) ON DELETE CASCADE;


--
-- Name: mfa_challenges mfa_challenges_auth_factor_id_fkey; Type: FK CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.mfa_challenges
    ADD CONSTRAINT mfa_challenges_auth_factor_id_fkey FOREIGN KEY (factor_id) REFERENCES auth.mfa_factors(id) ON DELETE CASCADE;


--
-- Name: mfa_factors mfa_factors_user_id_fkey; Type: FK CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.mfa_factors
    ADD CONSTRAINT mfa_factors_user_id_fkey FOREIGN KEY (user_id) REFERENCES auth.users(id) ON DELETE CASCADE;


--
-- Name: oauth_authorizations oauth_authorizations_client_id_fkey; Type: FK CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.oauth_authorizations
    ADD CONSTRAINT oauth_authorizations_client_id_fkey FOREIGN KEY (client_id) REFERENCES auth.oauth_clients(id) ON DELETE CASCADE;


--
-- Name: oauth_authorizations oauth_authorizations_user_id_fkey; Type: FK CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.oauth_authorizations
    ADD CONSTRAINT oauth_authorizations_user_id_fkey FOREIGN KEY (user_id) REFERENCES auth.users(id) ON DELETE CASCADE;


--
-- Name: oauth_consents oauth_consents_client_id_fkey; Type: FK CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.oauth_consents
    ADD CONSTRAINT oauth_consents_client_id_fkey FOREIGN KEY (client_id) REFERENCES auth.oauth_clients(id) ON DELETE CASCADE;


--
-- Name: oauth_consents oauth_consents_user_id_fkey; Type: FK CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.oauth_consents
    ADD CONSTRAINT oauth_consents_user_id_fkey FOREIGN KEY (user_id) REFERENCES auth.users(id) ON DELETE CASCADE;


--
-- Name: one_time_tokens one_time_tokens_user_id_fkey; Type: FK CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.one_time_tokens
    ADD CONSTRAINT one_time_tokens_user_id_fkey FOREIGN KEY (user_id) REFERENCES auth.users(id) ON DELETE CASCADE;


--
-- Name: refresh_tokens refresh_tokens_session_id_fkey; Type: FK CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.refresh_tokens
    ADD CONSTRAINT refresh_tokens_session_id_fkey FOREIGN KEY (session_id) REFERENCES auth.sessions(id) ON DELETE CASCADE;


--
-- Name: saml_providers saml_providers_sso_provider_id_fkey; Type: FK CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.saml_providers
    ADD CONSTRAINT saml_providers_sso_provider_id_fkey FOREIGN KEY (sso_provider_id) REFERENCES auth.sso_providers(id) ON DELETE CASCADE;


--
-- Name: saml_relay_states saml_relay_states_flow_state_id_fkey; Type: FK CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.saml_relay_states
    ADD CONSTRAINT saml_relay_states_flow_state_id_fkey FOREIGN KEY (flow_state_id) REFERENCES auth.flow_state(id) ON DELETE CASCADE;


--
-- Name: saml_relay_states saml_relay_states_sso_provider_id_fkey; Type: FK CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.saml_relay_states
    ADD CONSTRAINT saml_relay_states_sso_provider_id_fkey FOREIGN KEY (sso_provider_id) REFERENCES auth.sso_providers(id) ON DELETE CASCADE;


--
-- Name: sessions sessions_oauth_client_id_fkey; Type: FK CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.sessions
    ADD CONSTRAINT sessions_oauth_client_id_fkey FOREIGN KEY (oauth_client_id) REFERENCES auth.oauth_clients(id) ON DELETE CASCADE;


--
-- Name: sessions sessions_user_id_fkey; Type: FK CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.sessions
    ADD CONSTRAINT sessions_user_id_fkey FOREIGN KEY (user_id) REFERENCES auth.users(id) ON DELETE CASCADE;


--
-- Name: sso_domains sso_domains_sso_provider_id_fkey; Type: FK CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.sso_domains
    ADD CONSTRAINT sso_domains_sso_provider_id_fkey FOREIGN KEY (sso_provider_id) REFERENCES auth.sso_providers(id) ON DELETE CASCADE;


--
-- Name: webauthn_challenges webauthn_challenges_user_id_fkey; Type: FK CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.webauthn_challenges
    ADD CONSTRAINT webauthn_challenges_user_id_fkey FOREIGN KEY (user_id) REFERENCES auth.users(id) ON DELETE CASCADE;


--
-- Name: webauthn_credentials webauthn_credentials_user_id_fkey; Type: FK CONSTRAINT; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE ONLY auth.webauthn_credentials
    ADD CONSTRAINT webauthn_credentials_user_id_fkey FOREIGN KEY (user_id) REFERENCES auth.users(id) ON DELETE CASCADE;


--
-- Name: activity_calendars activity_calendars_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.activity_calendars
    ADD CONSTRAINT activity_calendars_document_id_foreign FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE;


--
-- Name: activity_proposals activity_proposals_calendar_activity_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.activity_proposals
    ADD CONSTRAINT activity_proposals_calendar_activity_id_foreign FOREIGN KEY (calendar_activity_id) REFERENCES public.calendar_activities(id) ON DELETE SET NULL;


--
-- Name: activity_proposals activity_proposals_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.activity_proposals
    ADD CONSTRAINT activity_proposals_document_id_foreign FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE;


--
-- Name: after_activity_reports after_activity_reports_activity_proposal_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.after_activity_reports
    ADD CONSTRAINT after_activity_reports_activity_proposal_id_foreign FOREIGN KEY (activity_proposal_id) REFERENCES public.activity_proposals(id) ON DELETE RESTRICT;


--
-- Name: after_activity_reports after_activity_reports_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.after_activity_reports
    ADD CONSTRAINT after_activity_reports_document_id_foreign FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE;


--
-- Name: approval_notifications approval_notifications_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.approval_notifications
    ADD CONSTRAINT approval_notifications_document_id_foreign FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE;


--
-- Name: approval_notifications approval_notifications_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.approval_notifications
    ADD CONSTRAINT approval_notifications_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: calendar_activities calendar_activities_activity_calendar_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.calendar_activities
    ADD CONSTRAINT calendar_activities_activity_calendar_id_foreign FOREIGN KEY (activity_calendar_id) REFERENCES public.activity_calendars(id) ON DELETE CASCADE;


--
-- Name: document_attachments document_attachments_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.document_attachments
    ADD CONSTRAINT document_attachments_document_id_foreign FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE;


--
-- Name: document_attachments document_attachments_uploaded_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.document_attachments
    ADD CONSTRAINT document_attachments_uploaded_by_foreign FOREIGN KEY (uploaded_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: document_step_approvals document_step_approvals_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.document_step_approvals
    ADD CONSTRAINT document_step_approvals_document_id_foreign FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE;


--
-- Name: document_step_approvals document_step_approvals_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.document_step_approvals
    ADD CONSTRAINT document_step_approvals_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: document_step_approvals document_step_approvals_workflow_step_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.document_step_approvals
    ADD CONSTRAINT document_step_approvals_workflow_step_id_foreign FOREIGN KEY (workflow_step_id) REFERENCES public.workflow_steps(id) ON DELETE CASCADE;


--
-- Name: document_transitions document_transitions_actor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.document_transitions
    ADD CONSTRAINT document_transitions_actor_id_foreign FOREIGN KEY (actor_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: document_transitions document_transitions_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.document_transitions
    ADD CONSTRAINT document_transitions_document_id_foreign FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE;


--
-- Name: documents documents_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE;


--
-- Name: documents documents_submitted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_submitted_by_foreign FOREIGN KEY (submitted_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: documents documents_workflow_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_workflow_template_id_foreign FOREIGN KEY (workflow_template_id) REFERENCES public.workflow_templates(id) ON DELETE SET NULL;


--
-- Name: email_verification_codes email_verification_codes_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.email_verification_codes
    ADD CONSTRAINT email_verification_codes_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: organization_join_requests organization_join_requests_decided_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.organization_join_requests
    ADD CONSTRAINT organization_join_requests_decided_by_foreign FOREIGN KEY (decided_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: organization_join_requests organization_join_requests_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.organization_join_requests
    ADD CONSTRAINT organization_join_requests_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE;


--
-- Name: organization_join_requests organization_join_requests_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.organization_join_requests
    ADD CONSTRAINT organization_join_requests_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: organization_memberships organization_memberships_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.organization_memberships
    ADD CONSTRAINT organization_memberships_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE;


--
-- Name: organization_memberships organization_memberships_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.organization_memberships
    ADD CONSTRAINT organization_memberships_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: organization_registration_details organization_registration_details_adviser_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.organization_registration_details
    ADD CONSTRAINT organization_registration_details_adviser_id_foreign FOREIGN KEY (adviser_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: organization_registration_details organization_registration_details_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.organization_registration_details
    ADD CONSTRAINT organization_registration_details_document_id_foreign FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE;


--
-- Name: organizations organizations_program_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.organizations
    ADD CONSTRAINT organizations_program_id_foreign FOREIGN KEY (program_id) REFERENCES public.programs(id) ON DELETE SET NULL;


--
-- Name: organizations organizations_school_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.organizations
    ADD CONSTRAINT organizations_school_id_foreign FOREIGN KEY (school_id) REFERENCES public.schools(id) ON DELETE CASCADE;


--
-- Name: passkeys passkeys_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.passkeys
    ADD CONSTRAINT passkeys_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: programs programs_school_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.programs
    ADD CONSTRAINT programs_school_id_foreign FOREIGN KEY (school_id) REFERENCES public.schools(id) ON DELETE CASCADE;


--
-- Name: role_assignments role_assignments_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.role_assignments
    ADD CONSTRAINT role_assignments_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE SET NULL;


--
-- Name: role_assignments role_assignments_program_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.role_assignments
    ADD CONSTRAINT role_assignments_program_id_foreign FOREIGN KEY (program_id) REFERENCES public.programs(id) ON DELETE CASCADE;


--
-- Name: role_assignments role_assignments_school_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.role_assignments
    ADD CONSTRAINT role_assignments_school_id_foreign FOREIGN KEY (school_id) REFERENCES public.schools(id) ON DELETE CASCADE;


--
-- Name: role_assignments role_assignments_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.role_assignments
    ADD CONSTRAINT role_assignments_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: workflow_steps workflow_steps_workflow_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.workflow_steps
    ADD CONSTRAINT workflow_steps_workflow_template_id_foreign FOREIGN KEY (workflow_template_id) REFERENCES public.workflow_templates(id) ON DELETE CASCADE;


--
-- Name: objects objects_bucketId_fkey; Type: FK CONSTRAINT; Schema: storage; Owner: supabase_storage_admin
--

ALTER TABLE ONLY storage.objects
    ADD CONSTRAINT "objects_bucketId_fkey" FOREIGN KEY (bucket_id) REFERENCES storage.buckets(id);


--
-- Name: s3_multipart_uploads s3_multipart_uploads_bucket_id_fkey; Type: FK CONSTRAINT; Schema: storage; Owner: supabase_storage_admin
--

ALTER TABLE ONLY storage.s3_multipart_uploads
    ADD CONSTRAINT s3_multipart_uploads_bucket_id_fkey FOREIGN KEY (bucket_id) REFERENCES storage.buckets(id);


--
-- Name: s3_multipart_uploads_parts s3_multipart_uploads_parts_bucket_id_fkey; Type: FK CONSTRAINT; Schema: storage; Owner: supabase_storage_admin
--

ALTER TABLE ONLY storage.s3_multipart_uploads_parts
    ADD CONSTRAINT s3_multipart_uploads_parts_bucket_id_fkey FOREIGN KEY (bucket_id) REFERENCES storage.buckets(id);


--
-- Name: s3_multipart_uploads_parts s3_multipart_uploads_parts_upload_id_fkey; Type: FK CONSTRAINT; Schema: storage; Owner: supabase_storage_admin
--

ALTER TABLE ONLY storage.s3_multipart_uploads_parts
    ADD CONSTRAINT s3_multipart_uploads_parts_upload_id_fkey FOREIGN KEY (upload_id) REFERENCES storage.s3_multipart_uploads(id) ON DELETE CASCADE;


--
-- Name: vector_indexes vector_indexes_bucket_id_fkey; Type: FK CONSTRAINT; Schema: storage; Owner: supabase_storage_admin
--

ALTER TABLE ONLY storage.vector_indexes
    ADD CONSTRAINT vector_indexes_bucket_id_fkey FOREIGN KEY (bucket_id) REFERENCES storage.buckets_vectors(id);


--
-- Name: audit_log_entries; Type: ROW SECURITY; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE auth.audit_log_entries ENABLE ROW LEVEL SECURITY;

--
-- Name: flow_state; Type: ROW SECURITY; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE auth.flow_state ENABLE ROW LEVEL SECURITY;

--
-- Name: identities; Type: ROW SECURITY; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE auth.identities ENABLE ROW LEVEL SECURITY;

--
-- Name: instances; Type: ROW SECURITY; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE auth.instances ENABLE ROW LEVEL SECURITY;

--
-- Name: mfa_amr_claims; Type: ROW SECURITY; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE auth.mfa_amr_claims ENABLE ROW LEVEL SECURITY;

--
-- Name: mfa_challenges; Type: ROW SECURITY; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE auth.mfa_challenges ENABLE ROW LEVEL SECURITY;

--
-- Name: mfa_factors; Type: ROW SECURITY; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE auth.mfa_factors ENABLE ROW LEVEL SECURITY;

--
-- Name: one_time_tokens; Type: ROW SECURITY; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE auth.one_time_tokens ENABLE ROW LEVEL SECURITY;

--
-- Name: refresh_tokens; Type: ROW SECURITY; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE auth.refresh_tokens ENABLE ROW LEVEL SECURITY;

--
-- Name: saml_providers; Type: ROW SECURITY; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE auth.saml_providers ENABLE ROW LEVEL SECURITY;

--
-- Name: saml_relay_states; Type: ROW SECURITY; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE auth.saml_relay_states ENABLE ROW LEVEL SECURITY;

--
-- Name: schema_migrations; Type: ROW SECURITY; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE auth.schema_migrations ENABLE ROW LEVEL SECURITY;

--
-- Name: sessions; Type: ROW SECURITY; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE auth.sessions ENABLE ROW LEVEL SECURITY;

--
-- Name: sso_domains; Type: ROW SECURITY; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE auth.sso_domains ENABLE ROW LEVEL SECURITY;

--
-- Name: sso_providers; Type: ROW SECURITY; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE auth.sso_providers ENABLE ROW LEVEL SECURITY;

--
-- Name: users; Type: ROW SECURITY; Schema: auth; Owner: supabase_auth_admin
--

ALTER TABLE auth.users ENABLE ROW LEVEL SECURITY;

--
-- Name: messages; Type: ROW SECURITY; Schema: realtime; Owner: supabase_realtime_admin
--

ALTER TABLE realtime.messages ENABLE ROW LEVEL SECURITY;

--
-- Name: buckets; Type: ROW SECURITY; Schema: storage; Owner: supabase_storage_admin
--

ALTER TABLE storage.buckets ENABLE ROW LEVEL SECURITY;

--
-- Name: buckets_analytics; Type: ROW SECURITY; Schema: storage; Owner: supabase_storage_admin
--

ALTER TABLE storage.buckets_analytics ENABLE ROW LEVEL SECURITY;

--
-- Name: buckets_vectors; Type: ROW SECURITY; Schema: storage; Owner: supabase_storage_admin
--

ALTER TABLE storage.buckets_vectors ENABLE ROW LEVEL SECURITY;

--
-- Name: migrations; Type: ROW SECURITY; Schema: storage; Owner: supabase_storage_admin
--

ALTER TABLE storage.migrations ENABLE ROW LEVEL SECURITY;

--
-- Name: objects; Type: ROW SECURITY; Schema: storage; Owner: supabase_storage_admin
--

ALTER TABLE storage.objects ENABLE ROW LEVEL SECURITY;

--
-- Name: s3_multipart_uploads; Type: ROW SECURITY; Schema: storage; Owner: supabase_storage_admin
--

ALTER TABLE storage.s3_multipart_uploads ENABLE ROW LEVEL SECURITY;

--
-- Name: s3_multipart_uploads_parts; Type: ROW SECURITY; Schema: storage; Owner: supabase_storage_admin
--

ALTER TABLE storage.s3_multipart_uploads_parts ENABLE ROW LEVEL SECURITY;

--
-- Name: vector_indexes; Type: ROW SECURITY; Schema: storage; Owner: supabase_storage_admin
--

ALTER TABLE storage.vector_indexes ENABLE ROW LEVEL SECURITY;

--
-- Name: supabase_realtime; Type: PUBLICATION; Schema: -; Owner: postgres
--

CREATE PUBLICATION supabase_realtime WITH (publish = 'insert, update, delete, truncate');


ALTER PUBLICATION supabase_realtime OWNER TO postgres;

--
-- Name: SCHEMA auth; Type: ACL; Schema: -; Owner: supabase_admin
--

GRANT USAGE ON SCHEMA auth TO anon;
GRANT USAGE ON SCHEMA auth TO authenticated;
GRANT USAGE ON SCHEMA auth TO service_role;
GRANT ALL ON SCHEMA auth TO supabase_auth_admin;
GRANT ALL ON SCHEMA auth TO dashboard_user;
GRANT USAGE ON SCHEMA auth TO postgres;


--
-- Name: SCHEMA extensions; Type: ACL; Schema: -; Owner: postgres
--

GRANT USAGE ON SCHEMA extensions TO anon;
GRANT USAGE ON SCHEMA extensions TO authenticated;
GRANT USAGE ON SCHEMA extensions TO service_role;
GRANT ALL ON SCHEMA extensions TO dashboard_user;


--
-- Name: SCHEMA public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE USAGE ON SCHEMA public FROM PUBLIC;


--
-- Name: SCHEMA realtime; Type: ACL; Schema: -; Owner: supabase_admin
--

GRANT USAGE ON SCHEMA realtime TO postgres WITH GRANT OPTION;
GRANT USAGE ON SCHEMA realtime TO anon;
GRANT USAGE ON SCHEMA realtime TO service_role;
GRANT ALL ON SCHEMA realtime TO supabase_realtime_admin WITH GRANT OPTION;
GRANT USAGE ON SCHEMA realtime TO authenticated;


--
-- Name: SCHEMA storage; Type: ACL; Schema: -; Owner: supabase_admin
--

GRANT USAGE ON SCHEMA storage TO postgres WITH GRANT OPTION;
GRANT USAGE ON SCHEMA storage TO anon;
GRANT USAGE ON SCHEMA storage TO authenticated;
GRANT USAGE ON SCHEMA storage TO service_role;
GRANT ALL ON SCHEMA storage TO supabase_storage_admin WITH GRANT OPTION;
GRANT ALL ON SCHEMA storage TO dashboard_user;


--
-- Name: SCHEMA vault; Type: ACL; Schema: -; Owner: supabase_admin
--

GRANT USAGE ON SCHEMA vault TO postgres WITH GRANT OPTION;
GRANT USAGE ON SCHEMA vault TO service_role;


--
-- Name: FUNCTION email(); Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT ALL ON FUNCTION auth.email() TO dashboard_user;


--
-- Name: FUNCTION jwt(); Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT ALL ON FUNCTION auth.jwt() TO postgres;
GRANT ALL ON FUNCTION auth.jwt() TO dashboard_user;


--
-- Name: FUNCTION role(); Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT ALL ON FUNCTION auth.role() TO dashboard_user;


--
-- Name: FUNCTION uid(); Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT ALL ON FUNCTION auth.uid() TO dashboard_user;


--
-- Name: FUNCTION armor(bytea); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.armor(bytea) FROM postgres;
GRANT ALL ON FUNCTION extensions.armor(bytea) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.armor(bytea) TO dashboard_user;


--
-- Name: FUNCTION armor(bytea, text[], text[]); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.armor(bytea, text[], text[]) FROM postgres;
GRANT ALL ON FUNCTION extensions.armor(bytea, text[], text[]) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.armor(bytea, text[], text[]) TO dashboard_user;


--
-- Name: FUNCTION crypt(text, text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.crypt(text, text) FROM postgres;
GRANT ALL ON FUNCTION extensions.crypt(text, text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.crypt(text, text) TO dashboard_user;


--
-- Name: FUNCTION dearmor(text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.dearmor(text) FROM postgres;
GRANT ALL ON FUNCTION extensions.dearmor(text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.dearmor(text) TO dashboard_user;


--
-- Name: FUNCTION decrypt(bytea, bytea, text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.decrypt(bytea, bytea, text) FROM postgres;
GRANT ALL ON FUNCTION extensions.decrypt(bytea, bytea, text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.decrypt(bytea, bytea, text) TO dashboard_user;


--
-- Name: FUNCTION decrypt_iv(bytea, bytea, bytea, text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.decrypt_iv(bytea, bytea, bytea, text) FROM postgres;
GRANT ALL ON FUNCTION extensions.decrypt_iv(bytea, bytea, bytea, text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.decrypt_iv(bytea, bytea, bytea, text) TO dashboard_user;


--
-- Name: FUNCTION digest(bytea, text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.digest(bytea, text) FROM postgres;
GRANT ALL ON FUNCTION extensions.digest(bytea, text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.digest(bytea, text) TO dashboard_user;


--
-- Name: FUNCTION digest(text, text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.digest(text, text) FROM postgres;
GRANT ALL ON FUNCTION extensions.digest(text, text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.digest(text, text) TO dashboard_user;


--
-- Name: FUNCTION encrypt(bytea, bytea, text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.encrypt(bytea, bytea, text) FROM postgres;
GRANT ALL ON FUNCTION extensions.encrypt(bytea, bytea, text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.encrypt(bytea, bytea, text) TO dashboard_user;


--
-- Name: FUNCTION encrypt_iv(bytea, bytea, bytea, text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.encrypt_iv(bytea, bytea, bytea, text) FROM postgres;
GRANT ALL ON FUNCTION extensions.encrypt_iv(bytea, bytea, bytea, text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.encrypt_iv(bytea, bytea, bytea, text) TO dashboard_user;


--
-- Name: FUNCTION gen_random_bytes(integer); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.gen_random_bytes(integer) FROM postgres;
GRANT ALL ON FUNCTION extensions.gen_random_bytes(integer) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.gen_random_bytes(integer) TO dashboard_user;


--
-- Name: FUNCTION gen_random_uuid(); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.gen_random_uuid() FROM postgres;
GRANT ALL ON FUNCTION extensions.gen_random_uuid() TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.gen_random_uuid() TO dashboard_user;


--
-- Name: FUNCTION gen_salt(text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.gen_salt(text) FROM postgres;
GRANT ALL ON FUNCTION extensions.gen_salt(text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.gen_salt(text) TO dashboard_user;


--
-- Name: FUNCTION gen_salt(text, integer); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.gen_salt(text, integer) FROM postgres;
GRANT ALL ON FUNCTION extensions.gen_salt(text, integer) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.gen_salt(text, integer) TO dashboard_user;


--
-- Name: FUNCTION grant_pg_cron_access(); Type: ACL; Schema: extensions; Owner: supabase_admin
--

REVOKE ALL ON FUNCTION extensions.grant_pg_cron_access() FROM supabase_admin;
GRANT ALL ON FUNCTION extensions.grant_pg_cron_access() TO supabase_admin WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.grant_pg_cron_access() TO dashboard_user;


--
-- Name: FUNCTION grant_pg_graphql_access(); Type: ACL; Schema: extensions; Owner: supabase_admin
--

GRANT ALL ON FUNCTION extensions.grant_pg_graphql_access() TO postgres WITH GRANT OPTION;


--
-- Name: FUNCTION grant_pg_net_access(); Type: ACL; Schema: extensions; Owner: supabase_admin
--

REVOKE ALL ON FUNCTION extensions.grant_pg_net_access() FROM supabase_admin;
GRANT ALL ON FUNCTION extensions.grant_pg_net_access() TO supabase_admin WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.grant_pg_net_access() TO dashboard_user;


--
-- Name: FUNCTION hmac(bytea, bytea, text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.hmac(bytea, bytea, text) FROM postgres;
GRANT ALL ON FUNCTION extensions.hmac(bytea, bytea, text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.hmac(bytea, bytea, text) TO dashboard_user;


--
-- Name: FUNCTION hmac(text, text, text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.hmac(text, text, text) FROM postgres;
GRANT ALL ON FUNCTION extensions.hmac(text, text, text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.hmac(text, text, text) TO dashboard_user;


--
-- Name: FUNCTION pg_stat_statements(showtext boolean, OUT userid oid, OUT dbid oid, OUT toplevel boolean, OUT queryid bigint, OUT query text, OUT plans bigint, OUT total_plan_time double precision, OUT min_plan_time double precision, OUT max_plan_time double precision, OUT mean_plan_time double precision, OUT stddev_plan_time double precision, OUT calls bigint, OUT total_exec_time double precision, OUT min_exec_time double precision, OUT max_exec_time double precision, OUT mean_exec_time double precision, OUT stddev_exec_time double precision, OUT rows bigint, OUT shared_blks_hit bigint, OUT shared_blks_read bigint, OUT shared_blks_dirtied bigint, OUT shared_blks_written bigint, OUT local_blks_hit bigint, OUT local_blks_read bigint, OUT local_blks_dirtied bigint, OUT local_blks_written bigint, OUT temp_blks_read bigint, OUT temp_blks_written bigint, OUT shared_blk_read_time double precision, OUT shared_blk_write_time double precision, OUT local_blk_read_time double precision, OUT local_blk_write_time double precision, OUT temp_blk_read_time double precision, OUT temp_blk_write_time double precision, OUT wal_records bigint, OUT wal_fpi bigint, OUT wal_bytes numeric, OUT jit_functions bigint, OUT jit_generation_time double precision, OUT jit_inlining_count bigint, OUT jit_inlining_time double precision, OUT jit_optimization_count bigint, OUT jit_optimization_time double precision, OUT jit_emission_count bigint, OUT jit_emission_time double precision, OUT jit_deform_count bigint, OUT jit_deform_time double precision, OUT stats_since timestamp with time zone, OUT minmax_stats_since timestamp with time zone); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.pg_stat_statements(showtext boolean, OUT userid oid, OUT dbid oid, OUT toplevel boolean, OUT queryid bigint, OUT query text, OUT plans bigint, OUT total_plan_time double precision, OUT min_plan_time double precision, OUT max_plan_time double precision, OUT mean_plan_time double precision, OUT stddev_plan_time double precision, OUT calls bigint, OUT total_exec_time double precision, OUT min_exec_time double precision, OUT max_exec_time double precision, OUT mean_exec_time double precision, OUT stddev_exec_time double precision, OUT rows bigint, OUT shared_blks_hit bigint, OUT shared_blks_read bigint, OUT shared_blks_dirtied bigint, OUT shared_blks_written bigint, OUT local_blks_hit bigint, OUT local_blks_read bigint, OUT local_blks_dirtied bigint, OUT local_blks_written bigint, OUT temp_blks_read bigint, OUT temp_blks_written bigint, OUT shared_blk_read_time double precision, OUT shared_blk_write_time double precision, OUT local_blk_read_time double precision, OUT local_blk_write_time double precision, OUT temp_blk_read_time double precision, OUT temp_blk_write_time double precision, OUT wal_records bigint, OUT wal_fpi bigint, OUT wal_bytes numeric, OUT jit_functions bigint, OUT jit_generation_time double precision, OUT jit_inlining_count bigint, OUT jit_inlining_time double precision, OUT jit_optimization_count bigint, OUT jit_optimization_time double precision, OUT jit_emission_count bigint, OUT jit_emission_time double precision, OUT jit_deform_count bigint, OUT jit_deform_time double precision, OUT stats_since timestamp with time zone, OUT minmax_stats_since timestamp with time zone) FROM postgres;
GRANT ALL ON FUNCTION extensions.pg_stat_statements(showtext boolean, OUT userid oid, OUT dbid oid, OUT toplevel boolean, OUT queryid bigint, OUT query text, OUT plans bigint, OUT total_plan_time double precision, OUT min_plan_time double precision, OUT max_plan_time double precision, OUT mean_plan_time double precision, OUT stddev_plan_time double precision, OUT calls bigint, OUT total_exec_time double precision, OUT min_exec_time double precision, OUT max_exec_time double precision, OUT mean_exec_time double precision, OUT stddev_exec_time double precision, OUT rows bigint, OUT shared_blks_hit bigint, OUT shared_blks_read bigint, OUT shared_blks_dirtied bigint, OUT shared_blks_written bigint, OUT local_blks_hit bigint, OUT local_blks_read bigint, OUT local_blks_dirtied bigint, OUT local_blks_written bigint, OUT temp_blks_read bigint, OUT temp_blks_written bigint, OUT shared_blk_read_time double precision, OUT shared_blk_write_time double precision, OUT local_blk_read_time double precision, OUT local_blk_write_time double precision, OUT temp_blk_read_time double precision, OUT temp_blk_write_time double precision, OUT wal_records bigint, OUT wal_fpi bigint, OUT wal_bytes numeric, OUT jit_functions bigint, OUT jit_generation_time double precision, OUT jit_inlining_count bigint, OUT jit_inlining_time double precision, OUT jit_optimization_count bigint, OUT jit_optimization_time double precision, OUT jit_emission_count bigint, OUT jit_emission_time double precision, OUT jit_deform_count bigint, OUT jit_deform_time double precision, OUT stats_since timestamp with time zone, OUT minmax_stats_since timestamp with time zone) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.pg_stat_statements(showtext boolean, OUT userid oid, OUT dbid oid, OUT toplevel boolean, OUT queryid bigint, OUT query text, OUT plans bigint, OUT total_plan_time double precision, OUT min_plan_time double precision, OUT max_plan_time double precision, OUT mean_plan_time double precision, OUT stddev_plan_time double precision, OUT calls bigint, OUT total_exec_time double precision, OUT min_exec_time double precision, OUT max_exec_time double precision, OUT mean_exec_time double precision, OUT stddev_exec_time double precision, OUT rows bigint, OUT shared_blks_hit bigint, OUT shared_blks_read bigint, OUT shared_blks_dirtied bigint, OUT shared_blks_written bigint, OUT local_blks_hit bigint, OUT local_blks_read bigint, OUT local_blks_dirtied bigint, OUT local_blks_written bigint, OUT temp_blks_read bigint, OUT temp_blks_written bigint, OUT shared_blk_read_time double precision, OUT shared_blk_write_time double precision, OUT local_blk_read_time double precision, OUT local_blk_write_time double precision, OUT temp_blk_read_time double precision, OUT temp_blk_write_time double precision, OUT wal_records bigint, OUT wal_fpi bigint, OUT wal_bytes numeric, OUT jit_functions bigint, OUT jit_generation_time double precision, OUT jit_inlining_count bigint, OUT jit_inlining_time double precision, OUT jit_optimization_count bigint, OUT jit_optimization_time double precision, OUT jit_emission_count bigint, OUT jit_emission_time double precision, OUT jit_deform_count bigint, OUT jit_deform_time double precision, OUT stats_since timestamp with time zone, OUT minmax_stats_since timestamp with time zone) TO dashboard_user;


--
-- Name: FUNCTION pg_stat_statements_info(OUT dealloc bigint, OUT stats_reset timestamp with time zone); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.pg_stat_statements_info(OUT dealloc bigint, OUT stats_reset timestamp with time zone) FROM postgres;
GRANT ALL ON FUNCTION extensions.pg_stat_statements_info(OUT dealloc bigint, OUT stats_reset timestamp with time zone) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.pg_stat_statements_info(OUT dealloc bigint, OUT stats_reset timestamp with time zone) TO dashboard_user;


--
-- Name: FUNCTION pg_stat_statements_reset(userid oid, dbid oid, queryid bigint, minmax_only boolean); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.pg_stat_statements_reset(userid oid, dbid oid, queryid bigint, minmax_only boolean) FROM postgres;
GRANT ALL ON FUNCTION extensions.pg_stat_statements_reset(userid oid, dbid oid, queryid bigint, minmax_only boolean) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.pg_stat_statements_reset(userid oid, dbid oid, queryid bigint, minmax_only boolean) TO dashboard_user;


--
-- Name: FUNCTION pgp_armor_headers(text, OUT key text, OUT value text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.pgp_armor_headers(text, OUT key text, OUT value text) FROM postgres;
GRANT ALL ON FUNCTION extensions.pgp_armor_headers(text, OUT key text, OUT value text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.pgp_armor_headers(text, OUT key text, OUT value text) TO dashboard_user;


--
-- Name: FUNCTION pgp_key_id(bytea); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.pgp_key_id(bytea) FROM postgres;
GRANT ALL ON FUNCTION extensions.pgp_key_id(bytea) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.pgp_key_id(bytea) TO dashboard_user;


--
-- Name: FUNCTION pgp_pub_decrypt(bytea, bytea); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.pgp_pub_decrypt(bytea, bytea) FROM postgres;
GRANT ALL ON FUNCTION extensions.pgp_pub_decrypt(bytea, bytea) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.pgp_pub_decrypt(bytea, bytea) TO dashboard_user;


--
-- Name: FUNCTION pgp_pub_decrypt(bytea, bytea, text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.pgp_pub_decrypt(bytea, bytea, text) FROM postgres;
GRANT ALL ON FUNCTION extensions.pgp_pub_decrypt(bytea, bytea, text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.pgp_pub_decrypt(bytea, bytea, text) TO dashboard_user;


--
-- Name: FUNCTION pgp_pub_decrypt(bytea, bytea, text, text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.pgp_pub_decrypt(bytea, bytea, text, text) FROM postgres;
GRANT ALL ON FUNCTION extensions.pgp_pub_decrypt(bytea, bytea, text, text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.pgp_pub_decrypt(bytea, bytea, text, text) TO dashboard_user;


--
-- Name: FUNCTION pgp_pub_decrypt_bytea(bytea, bytea); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.pgp_pub_decrypt_bytea(bytea, bytea) FROM postgres;
GRANT ALL ON FUNCTION extensions.pgp_pub_decrypt_bytea(bytea, bytea) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.pgp_pub_decrypt_bytea(bytea, bytea) TO dashboard_user;


--
-- Name: FUNCTION pgp_pub_decrypt_bytea(bytea, bytea, text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.pgp_pub_decrypt_bytea(bytea, bytea, text) FROM postgres;
GRANT ALL ON FUNCTION extensions.pgp_pub_decrypt_bytea(bytea, bytea, text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.pgp_pub_decrypt_bytea(bytea, bytea, text) TO dashboard_user;


--
-- Name: FUNCTION pgp_pub_decrypt_bytea(bytea, bytea, text, text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.pgp_pub_decrypt_bytea(bytea, bytea, text, text) FROM postgres;
GRANT ALL ON FUNCTION extensions.pgp_pub_decrypt_bytea(bytea, bytea, text, text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.pgp_pub_decrypt_bytea(bytea, bytea, text, text) TO dashboard_user;


--
-- Name: FUNCTION pgp_pub_encrypt(text, bytea); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.pgp_pub_encrypt(text, bytea) FROM postgres;
GRANT ALL ON FUNCTION extensions.pgp_pub_encrypt(text, bytea) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.pgp_pub_encrypt(text, bytea) TO dashboard_user;


--
-- Name: FUNCTION pgp_pub_encrypt(text, bytea, text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.pgp_pub_encrypt(text, bytea, text) FROM postgres;
GRANT ALL ON FUNCTION extensions.pgp_pub_encrypt(text, bytea, text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.pgp_pub_encrypt(text, bytea, text) TO dashboard_user;


--
-- Name: FUNCTION pgp_pub_encrypt_bytea(bytea, bytea); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.pgp_pub_encrypt_bytea(bytea, bytea) FROM postgres;
GRANT ALL ON FUNCTION extensions.pgp_pub_encrypt_bytea(bytea, bytea) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.pgp_pub_encrypt_bytea(bytea, bytea) TO dashboard_user;


--
-- Name: FUNCTION pgp_pub_encrypt_bytea(bytea, bytea, text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.pgp_pub_encrypt_bytea(bytea, bytea, text) FROM postgres;
GRANT ALL ON FUNCTION extensions.pgp_pub_encrypt_bytea(bytea, bytea, text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.pgp_pub_encrypt_bytea(bytea, bytea, text) TO dashboard_user;


--
-- Name: FUNCTION pgp_sym_decrypt(bytea, text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.pgp_sym_decrypt(bytea, text) FROM postgres;
GRANT ALL ON FUNCTION extensions.pgp_sym_decrypt(bytea, text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.pgp_sym_decrypt(bytea, text) TO dashboard_user;


--
-- Name: FUNCTION pgp_sym_decrypt(bytea, text, text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.pgp_sym_decrypt(bytea, text, text) FROM postgres;
GRANT ALL ON FUNCTION extensions.pgp_sym_decrypt(bytea, text, text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.pgp_sym_decrypt(bytea, text, text) TO dashboard_user;


--
-- Name: FUNCTION pgp_sym_decrypt_bytea(bytea, text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.pgp_sym_decrypt_bytea(bytea, text) FROM postgres;
GRANT ALL ON FUNCTION extensions.pgp_sym_decrypt_bytea(bytea, text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.pgp_sym_decrypt_bytea(bytea, text) TO dashboard_user;


--
-- Name: FUNCTION pgp_sym_decrypt_bytea(bytea, text, text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.pgp_sym_decrypt_bytea(bytea, text, text) FROM postgres;
GRANT ALL ON FUNCTION extensions.pgp_sym_decrypt_bytea(bytea, text, text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.pgp_sym_decrypt_bytea(bytea, text, text) TO dashboard_user;


--
-- Name: FUNCTION pgp_sym_encrypt(text, text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.pgp_sym_encrypt(text, text) FROM postgres;
GRANT ALL ON FUNCTION extensions.pgp_sym_encrypt(text, text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.pgp_sym_encrypt(text, text) TO dashboard_user;


--
-- Name: FUNCTION pgp_sym_encrypt(text, text, text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.pgp_sym_encrypt(text, text, text) FROM postgres;
GRANT ALL ON FUNCTION extensions.pgp_sym_encrypt(text, text, text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.pgp_sym_encrypt(text, text, text) TO dashboard_user;


--
-- Name: FUNCTION pgp_sym_encrypt_bytea(bytea, text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.pgp_sym_encrypt_bytea(bytea, text) FROM postgres;
GRANT ALL ON FUNCTION extensions.pgp_sym_encrypt_bytea(bytea, text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.pgp_sym_encrypt_bytea(bytea, text) TO dashboard_user;


--
-- Name: FUNCTION pgp_sym_encrypt_bytea(bytea, text, text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.pgp_sym_encrypt_bytea(bytea, text, text) FROM postgres;
GRANT ALL ON FUNCTION extensions.pgp_sym_encrypt_bytea(bytea, text, text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.pgp_sym_encrypt_bytea(bytea, text, text) TO dashboard_user;


--
-- Name: FUNCTION pgrst_ddl_watch(); Type: ACL; Schema: extensions; Owner: supabase_admin
--

GRANT ALL ON FUNCTION extensions.pgrst_ddl_watch() TO postgres WITH GRANT OPTION;


--
-- Name: FUNCTION pgrst_drop_watch(); Type: ACL; Schema: extensions; Owner: supabase_admin
--

GRANT ALL ON FUNCTION extensions.pgrst_drop_watch() TO postgres WITH GRANT OPTION;


--
-- Name: FUNCTION set_graphql_placeholder(); Type: ACL; Schema: extensions; Owner: supabase_admin
--

GRANT ALL ON FUNCTION extensions.set_graphql_placeholder() TO postgres WITH GRANT OPTION;


--
-- Name: FUNCTION uuid_generate_v1(); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.uuid_generate_v1() FROM postgres;
GRANT ALL ON FUNCTION extensions.uuid_generate_v1() TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.uuid_generate_v1() TO dashboard_user;


--
-- Name: FUNCTION uuid_generate_v1mc(); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.uuid_generate_v1mc() FROM postgres;
GRANT ALL ON FUNCTION extensions.uuid_generate_v1mc() TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.uuid_generate_v1mc() TO dashboard_user;


--
-- Name: FUNCTION uuid_generate_v3(namespace uuid, name text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.uuid_generate_v3(namespace uuid, name text) FROM postgres;
GRANT ALL ON FUNCTION extensions.uuid_generate_v3(namespace uuid, name text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.uuid_generate_v3(namespace uuid, name text) TO dashboard_user;


--
-- Name: FUNCTION uuid_generate_v4(); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.uuid_generate_v4() FROM postgres;
GRANT ALL ON FUNCTION extensions.uuid_generate_v4() TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.uuid_generate_v4() TO dashboard_user;


--
-- Name: FUNCTION uuid_generate_v5(namespace uuid, name text); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.uuid_generate_v5(namespace uuid, name text) FROM postgres;
GRANT ALL ON FUNCTION extensions.uuid_generate_v5(namespace uuid, name text) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.uuid_generate_v5(namespace uuid, name text) TO dashboard_user;


--
-- Name: FUNCTION uuid_nil(); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.uuid_nil() FROM postgres;
GRANT ALL ON FUNCTION extensions.uuid_nil() TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.uuid_nil() TO dashboard_user;


--
-- Name: FUNCTION uuid_ns_dns(); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.uuid_ns_dns() FROM postgres;
GRANT ALL ON FUNCTION extensions.uuid_ns_dns() TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.uuid_ns_dns() TO dashboard_user;


--
-- Name: FUNCTION uuid_ns_oid(); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.uuid_ns_oid() FROM postgres;
GRANT ALL ON FUNCTION extensions.uuid_ns_oid() TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.uuid_ns_oid() TO dashboard_user;


--
-- Name: FUNCTION uuid_ns_url(); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.uuid_ns_url() FROM postgres;
GRANT ALL ON FUNCTION extensions.uuid_ns_url() TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.uuid_ns_url() TO dashboard_user;


--
-- Name: FUNCTION uuid_ns_x500(); Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON FUNCTION extensions.uuid_ns_x500() FROM postgres;
GRANT ALL ON FUNCTION extensions.uuid_ns_x500() TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION extensions.uuid_ns_x500() TO dashboard_user;


--
-- Name: FUNCTION graphql("operationName" text, query text, variables jsonb, extensions jsonb); Type: ACL; Schema: graphql_public; Owner: supabase_admin
--

GRANT ALL ON FUNCTION graphql_public.graphql("operationName" text, query text, variables jsonb, extensions jsonb) TO postgres;
GRANT ALL ON FUNCTION graphql_public.graphql("operationName" text, query text, variables jsonb, extensions jsonb) TO anon;
GRANT ALL ON FUNCTION graphql_public.graphql("operationName" text, query text, variables jsonb, extensions jsonb) TO authenticated;
GRANT ALL ON FUNCTION graphql_public.graphql("operationName" text, query text, variables jsonb, extensions jsonb) TO service_role;


--
-- Name: FUNCTION pg_reload_conf(); Type: ACL; Schema: pg_catalog; Owner: supabase_admin
--

GRANT ALL ON FUNCTION pg_catalog.pg_reload_conf() TO postgres WITH GRANT OPTION;


--
-- Name: FUNCTION get_auth(p_usename text); Type: ACL; Schema: pgbouncer; Owner: supabase_admin
--

REVOKE ALL ON FUNCTION pgbouncer.get_auth(p_usename text) FROM PUBLIC;
GRANT ALL ON FUNCTION pgbouncer.get_auth(p_usename text) TO pgbouncer;


--
-- Name: FUNCTION apply_rls(wal jsonb, max_record_bytes integer); Type: ACL; Schema: realtime; Owner: supabase_realtime_admin
--

GRANT ALL ON FUNCTION realtime.apply_rls(wal jsonb, max_record_bytes integer) TO postgres;
GRANT ALL ON FUNCTION realtime.apply_rls(wal jsonb, max_record_bytes integer) TO dashboard_user;
GRANT ALL ON FUNCTION realtime.apply_rls(wal jsonb, max_record_bytes integer) TO anon;
GRANT ALL ON FUNCTION realtime.apply_rls(wal jsonb, max_record_bytes integer) TO authenticated;
GRANT ALL ON FUNCTION realtime.apply_rls(wal jsonb, max_record_bytes integer) TO service_role;


--
-- Name: FUNCTION broadcast_changes(topic_name text, event_name text, operation text, table_name text, table_schema text, new record, old record, level text); Type: ACL; Schema: realtime; Owner: supabase_realtime_admin
--

GRANT ALL ON FUNCTION realtime.broadcast_changes(topic_name text, event_name text, operation text, table_name text, table_schema text, new record, old record, level text) TO postgres;
GRANT ALL ON FUNCTION realtime.broadcast_changes(topic_name text, event_name text, operation text, table_name text, table_schema text, new record, old record, level text) TO dashboard_user;


--
-- Name: FUNCTION build_prepared_statement_sql(prepared_statement_name text, entity regclass, columns realtime.wal_column[]); Type: ACL; Schema: realtime; Owner: supabase_realtime_admin
--

GRANT ALL ON FUNCTION realtime.build_prepared_statement_sql(prepared_statement_name text, entity regclass, columns realtime.wal_column[]) TO postgres;
GRANT ALL ON FUNCTION realtime.build_prepared_statement_sql(prepared_statement_name text, entity regclass, columns realtime.wal_column[]) TO dashboard_user;
GRANT ALL ON FUNCTION realtime.build_prepared_statement_sql(prepared_statement_name text, entity regclass, columns realtime.wal_column[]) TO anon;
GRANT ALL ON FUNCTION realtime.build_prepared_statement_sql(prepared_statement_name text, entity regclass, columns realtime.wal_column[]) TO authenticated;
GRANT ALL ON FUNCTION realtime.build_prepared_statement_sql(prepared_statement_name text, entity regclass, columns realtime.wal_column[]) TO service_role;


--
-- Name: FUNCTION "cast"(val text, type_ regtype); Type: ACL; Schema: realtime; Owner: supabase_realtime_admin
--

GRANT ALL ON FUNCTION realtime."cast"(val text, type_ regtype) TO postgres;
GRANT ALL ON FUNCTION realtime."cast"(val text, type_ regtype) TO dashboard_user;
GRANT ALL ON FUNCTION realtime."cast"(val text, type_ regtype) TO anon;
GRANT ALL ON FUNCTION realtime."cast"(val text, type_ regtype) TO authenticated;
GRANT ALL ON FUNCTION realtime."cast"(val text, type_ regtype) TO service_role;


--
-- Name: FUNCTION check_equality_op(op realtime.equality_op, type_ regtype, val_1 text, val_2 text); Type: ACL; Schema: realtime; Owner: supabase_realtime_admin
--

GRANT ALL ON FUNCTION realtime.check_equality_op(op realtime.equality_op, type_ regtype, val_1 text, val_2 text) TO postgres;
GRANT ALL ON FUNCTION realtime.check_equality_op(op realtime.equality_op, type_ regtype, val_1 text, val_2 text) TO dashboard_user;
GRANT ALL ON FUNCTION realtime.check_equality_op(op realtime.equality_op, type_ regtype, val_1 text, val_2 text) TO anon;
GRANT ALL ON FUNCTION realtime.check_equality_op(op realtime.equality_op, type_ regtype, val_1 text, val_2 text) TO authenticated;
GRANT ALL ON FUNCTION realtime.check_equality_op(op realtime.equality_op, type_ regtype, val_1 text, val_2 text) TO service_role;


--
-- Name: FUNCTION check_equality_op(op realtime.equality_op, type_ regtype, val_1 text, val_2 text, negate boolean); Type: ACL; Schema: realtime; Owner: supabase_realtime_admin
--

GRANT ALL ON FUNCTION realtime.check_equality_op(op realtime.equality_op, type_ regtype, val_1 text, val_2 text, negate boolean) TO postgres;
GRANT ALL ON FUNCTION realtime.check_equality_op(op realtime.equality_op, type_ regtype, val_1 text, val_2 text, negate boolean) TO dashboard_user;
GRANT ALL ON FUNCTION realtime.check_equality_op(op realtime.equality_op, type_ regtype, val_1 text, val_2 text, negate boolean) TO anon;
GRANT ALL ON FUNCTION realtime.check_equality_op(op realtime.equality_op, type_ regtype, val_1 text, val_2 text, negate boolean) TO authenticated;
GRANT ALL ON FUNCTION realtime.check_equality_op(op realtime.equality_op, type_ regtype, val_1 text, val_2 text, negate boolean) TO service_role;


--
-- Name: FUNCTION is_visible_through_filters(columns realtime.wal_column[], filters realtime.user_defined_filter[]); Type: ACL; Schema: realtime; Owner: supabase_realtime_admin
--

GRANT ALL ON FUNCTION realtime.is_visible_through_filters(columns realtime.wal_column[], filters realtime.user_defined_filter[]) TO postgres;
GRANT ALL ON FUNCTION realtime.is_visible_through_filters(columns realtime.wal_column[], filters realtime.user_defined_filter[]) TO dashboard_user;
GRANT ALL ON FUNCTION realtime.is_visible_through_filters(columns realtime.wal_column[], filters realtime.user_defined_filter[]) TO anon;
GRANT ALL ON FUNCTION realtime.is_visible_through_filters(columns realtime.wal_column[], filters realtime.user_defined_filter[]) TO authenticated;
GRANT ALL ON FUNCTION realtime.is_visible_through_filters(columns realtime.wal_column[], filters realtime.user_defined_filter[]) TO service_role;


--
-- Name: FUNCTION list_changes(publication name, slot_name name, max_changes integer, max_record_bytes integer); Type: ACL; Schema: realtime; Owner: supabase_realtime_admin
--

GRANT ALL ON FUNCTION realtime.list_changes(publication name, slot_name name, max_changes integer, max_record_bytes integer) TO postgres;
GRANT ALL ON FUNCTION realtime.list_changes(publication name, slot_name name, max_changes integer, max_record_bytes integer) TO dashboard_user;


--
-- Name: FUNCTION quote_wal2json(entity regclass); Type: ACL; Schema: realtime; Owner: supabase_realtime_admin
--

GRANT ALL ON FUNCTION realtime.quote_wal2json(entity regclass) TO postgres;
GRANT ALL ON FUNCTION realtime.quote_wal2json(entity regclass) TO dashboard_user;
GRANT ALL ON FUNCTION realtime.quote_wal2json(entity regclass) TO anon;
GRANT ALL ON FUNCTION realtime.quote_wal2json(entity regclass) TO authenticated;
GRANT ALL ON FUNCTION realtime.quote_wal2json(entity regclass) TO service_role;


--
-- Name: FUNCTION send(payload jsonb, event text, topic text, private boolean); Type: ACL; Schema: realtime; Owner: supabase_realtime_admin
--

GRANT ALL ON FUNCTION realtime.send(payload jsonb, event text, topic text, private boolean) TO postgres;
GRANT ALL ON FUNCTION realtime.send(payload jsonb, event text, topic text, private boolean) TO dashboard_user;


--
-- Name: FUNCTION send_binary(payload bytea, event text, topic text, private boolean); Type: ACL; Schema: realtime; Owner: supabase_realtime_admin
--

GRANT ALL ON FUNCTION realtime.send_binary(payload bytea, event text, topic text, private boolean) TO postgres;
GRANT ALL ON FUNCTION realtime.send_binary(payload bytea, event text, topic text, private boolean) TO dashboard_user;


--
-- Name: FUNCTION subscription_check_filters(); Type: ACL; Schema: realtime; Owner: supabase_realtime_admin
--

GRANT ALL ON FUNCTION realtime.subscription_check_filters() TO postgres;
GRANT ALL ON FUNCTION realtime.subscription_check_filters() TO dashboard_user;
GRANT ALL ON FUNCTION realtime.subscription_check_filters() TO anon;
GRANT ALL ON FUNCTION realtime.subscription_check_filters() TO authenticated;
GRANT ALL ON FUNCTION realtime.subscription_check_filters() TO service_role;


--
-- Name: FUNCTION to_regrole(role_name text); Type: ACL; Schema: realtime; Owner: supabase_realtime_admin
--

GRANT ALL ON FUNCTION realtime.to_regrole(role_name text) TO postgres;
GRANT ALL ON FUNCTION realtime.to_regrole(role_name text) TO dashboard_user;
GRANT ALL ON FUNCTION realtime.to_regrole(role_name text) TO anon;
GRANT ALL ON FUNCTION realtime.to_regrole(role_name text) TO authenticated;
GRANT ALL ON FUNCTION realtime.to_regrole(role_name text) TO service_role;


--
-- Name: FUNCTION topic(); Type: ACL; Schema: realtime; Owner: supabase_realtime_admin
--

GRANT ALL ON FUNCTION realtime.topic() TO postgres;
GRANT ALL ON FUNCTION realtime.topic() TO dashboard_user;


--
-- Name: FUNCTION wal2json_escape_identifier(name text); Type: ACL; Schema: realtime; Owner: supabase_realtime_admin
--

GRANT ALL ON FUNCTION realtime.wal2json_escape_identifier(name text) TO postgres;
GRANT ALL ON FUNCTION realtime.wal2json_escape_identifier(name text) TO dashboard_user;


--
-- Name: FUNCTION _crypto_aead_det_decrypt(message bytea, additional bytea, key_id bigint, context bytea, nonce bytea); Type: ACL; Schema: vault; Owner: supabase_admin
--

GRANT ALL ON FUNCTION vault._crypto_aead_det_decrypt(message bytea, additional bytea, key_id bigint, context bytea, nonce bytea) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION vault._crypto_aead_det_decrypt(message bytea, additional bytea, key_id bigint, context bytea, nonce bytea) TO service_role;


--
-- Name: FUNCTION create_secret(new_secret text, new_name text, new_description text, new_key_id uuid); Type: ACL; Schema: vault; Owner: supabase_admin
--

GRANT ALL ON FUNCTION vault.create_secret(new_secret text, new_name text, new_description text, new_key_id uuid) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION vault.create_secret(new_secret text, new_name text, new_description text, new_key_id uuid) TO service_role;


--
-- Name: FUNCTION update_secret(secret_id uuid, new_secret text, new_name text, new_description text, new_key_id uuid); Type: ACL; Schema: vault; Owner: supabase_admin
--

GRANT ALL ON FUNCTION vault.update_secret(secret_id uuid, new_secret text, new_name text, new_description text, new_key_id uuid) TO postgres WITH GRANT OPTION;
GRANT ALL ON FUNCTION vault.update_secret(secret_id uuid, new_secret text, new_name text, new_description text, new_key_id uuid) TO service_role;


--
-- Name: TABLE audit_log_entries; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT ALL ON TABLE auth.audit_log_entries TO dashboard_user;
GRANT INSERT,REFERENCES,DELETE,TRIGGER,TRUNCATE,MAINTAIN,UPDATE ON TABLE auth.audit_log_entries TO postgres;
GRANT SELECT ON TABLE auth.audit_log_entries TO postgres WITH GRANT OPTION;


--
-- Name: TABLE custom_oauth_providers; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT ALL ON TABLE auth.custom_oauth_providers TO postgres;
GRANT ALL ON TABLE auth.custom_oauth_providers TO dashboard_user;


--
-- Name: TABLE flow_state; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT INSERT,REFERENCES,DELETE,TRIGGER,TRUNCATE,MAINTAIN,UPDATE ON TABLE auth.flow_state TO postgres;
GRANT SELECT ON TABLE auth.flow_state TO postgres WITH GRANT OPTION;
GRANT ALL ON TABLE auth.flow_state TO dashboard_user;


--
-- Name: TABLE identities; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT INSERT,REFERENCES,DELETE,TRIGGER,TRUNCATE,MAINTAIN,UPDATE ON TABLE auth.identities TO postgres;
GRANT SELECT ON TABLE auth.identities TO postgres WITH GRANT OPTION;
GRANT ALL ON TABLE auth.identities TO dashboard_user;


--
-- Name: TABLE instances; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT ALL ON TABLE auth.instances TO dashboard_user;
GRANT INSERT,REFERENCES,DELETE,TRIGGER,TRUNCATE,MAINTAIN,UPDATE ON TABLE auth.instances TO postgres;
GRANT SELECT ON TABLE auth.instances TO postgres WITH GRANT OPTION;


--
-- Name: TABLE mfa_amr_claims; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT INSERT,REFERENCES,DELETE,TRIGGER,TRUNCATE,MAINTAIN,UPDATE ON TABLE auth.mfa_amr_claims TO postgres;
GRANT SELECT ON TABLE auth.mfa_amr_claims TO postgres WITH GRANT OPTION;
GRANT ALL ON TABLE auth.mfa_amr_claims TO dashboard_user;


--
-- Name: TABLE mfa_challenges; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT INSERT,REFERENCES,DELETE,TRIGGER,TRUNCATE,MAINTAIN,UPDATE ON TABLE auth.mfa_challenges TO postgres;
GRANT SELECT ON TABLE auth.mfa_challenges TO postgres WITH GRANT OPTION;
GRANT ALL ON TABLE auth.mfa_challenges TO dashboard_user;


--
-- Name: TABLE mfa_factors; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT INSERT,REFERENCES,DELETE,TRIGGER,TRUNCATE,MAINTAIN,UPDATE ON TABLE auth.mfa_factors TO postgres;
GRANT SELECT ON TABLE auth.mfa_factors TO postgres WITH GRANT OPTION;
GRANT ALL ON TABLE auth.mfa_factors TO dashboard_user;


--
-- Name: TABLE oauth_authorizations; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT ALL ON TABLE auth.oauth_authorizations TO postgres;
GRANT ALL ON TABLE auth.oauth_authorizations TO dashboard_user;


--
-- Name: TABLE oauth_client_states; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT ALL ON TABLE auth.oauth_client_states TO postgres;
GRANT ALL ON TABLE auth.oauth_client_states TO dashboard_user;


--
-- Name: TABLE oauth_clients; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT ALL ON TABLE auth.oauth_clients TO postgres;
GRANT ALL ON TABLE auth.oauth_clients TO dashboard_user;


--
-- Name: TABLE oauth_consents; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT ALL ON TABLE auth.oauth_consents TO postgres;
GRANT ALL ON TABLE auth.oauth_consents TO dashboard_user;


--
-- Name: TABLE one_time_tokens; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT INSERT,REFERENCES,DELETE,TRIGGER,TRUNCATE,MAINTAIN,UPDATE ON TABLE auth.one_time_tokens TO postgres;
GRANT SELECT ON TABLE auth.one_time_tokens TO postgres WITH GRANT OPTION;
GRANT ALL ON TABLE auth.one_time_tokens TO dashboard_user;


--
-- Name: TABLE refresh_tokens; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT ALL ON TABLE auth.refresh_tokens TO dashboard_user;
GRANT INSERT,REFERENCES,DELETE,TRIGGER,TRUNCATE,MAINTAIN,UPDATE ON TABLE auth.refresh_tokens TO postgres;
GRANT SELECT ON TABLE auth.refresh_tokens TO postgres WITH GRANT OPTION;


--
-- Name: SEQUENCE refresh_tokens_id_seq; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT ALL ON SEQUENCE auth.refresh_tokens_id_seq TO dashboard_user;
GRANT ALL ON SEQUENCE auth.refresh_tokens_id_seq TO postgres;


--
-- Name: TABLE saml_providers; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT INSERT,REFERENCES,DELETE,TRIGGER,TRUNCATE,MAINTAIN,UPDATE ON TABLE auth.saml_providers TO postgres;
GRANT SELECT ON TABLE auth.saml_providers TO postgres WITH GRANT OPTION;
GRANT ALL ON TABLE auth.saml_providers TO dashboard_user;


--
-- Name: TABLE saml_relay_states; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT INSERT,REFERENCES,DELETE,TRIGGER,TRUNCATE,MAINTAIN,UPDATE ON TABLE auth.saml_relay_states TO postgres;
GRANT SELECT ON TABLE auth.saml_relay_states TO postgres WITH GRANT OPTION;
GRANT ALL ON TABLE auth.saml_relay_states TO dashboard_user;


--
-- Name: TABLE schema_migrations; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT SELECT ON TABLE auth.schema_migrations TO postgres WITH GRANT OPTION;


--
-- Name: TABLE sessions; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT INSERT,REFERENCES,DELETE,TRIGGER,TRUNCATE,MAINTAIN,UPDATE ON TABLE auth.sessions TO postgres;
GRANT SELECT ON TABLE auth.sessions TO postgres WITH GRANT OPTION;
GRANT ALL ON TABLE auth.sessions TO dashboard_user;


--
-- Name: TABLE sso_domains; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT INSERT,REFERENCES,DELETE,TRIGGER,TRUNCATE,MAINTAIN,UPDATE ON TABLE auth.sso_domains TO postgres;
GRANT SELECT ON TABLE auth.sso_domains TO postgres WITH GRANT OPTION;
GRANT ALL ON TABLE auth.sso_domains TO dashboard_user;


--
-- Name: TABLE sso_providers; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT INSERT,REFERENCES,DELETE,TRIGGER,TRUNCATE,MAINTAIN,UPDATE ON TABLE auth.sso_providers TO postgres;
GRANT SELECT ON TABLE auth.sso_providers TO postgres WITH GRANT OPTION;
GRANT ALL ON TABLE auth.sso_providers TO dashboard_user;


--
-- Name: TABLE users; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT ALL ON TABLE auth.users TO dashboard_user;
GRANT INSERT,REFERENCES,DELETE,TRIGGER,TRUNCATE,MAINTAIN,UPDATE ON TABLE auth.users TO postgres;
GRANT SELECT ON TABLE auth.users TO postgres WITH GRANT OPTION;


--
-- Name: TABLE webauthn_challenges; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT ALL ON TABLE auth.webauthn_challenges TO postgres;
GRANT ALL ON TABLE auth.webauthn_challenges TO dashboard_user;


--
-- Name: TABLE webauthn_credentials; Type: ACL; Schema: auth; Owner: supabase_auth_admin
--

GRANT ALL ON TABLE auth.webauthn_credentials TO postgres;
GRANT ALL ON TABLE auth.webauthn_credentials TO dashboard_user;


--
-- Name: TABLE pg_stat_statements; Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON TABLE extensions.pg_stat_statements FROM postgres;
GRANT ALL ON TABLE extensions.pg_stat_statements TO postgres WITH GRANT OPTION;
GRANT ALL ON TABLE extensions.pg_stat_statements TO dashboard_user;


--
-- Name: TABLE pg_stat_statements_info; Type: ACL; Schema: extensions; Owner: postgres
--

REVOKE ALL ON TABLE extensions.pg_stat_statements_info FROM postgres;
GRANT ALL ON TABLE extensions.pg_stat_statements_info TO postgres WITH GRANT OPTION;
GRANT ALL ON TABLE extensions.pg_stat_statements_info TO dashboard_user;


--
-- Name: TABLE messages; Type: ACL; Schema: realtime; Owner: supabase_realtime_admin
--

GRANT ALL ON TABLE realtime.messages TO postgres;
GRANT ALL ON TABLE realtime.messages TO dashboard_user;
GRANT SELECT,INSERT,UPDATE ON TABLE realtime.messages TO anon;
GRANT SELECT,INSERT,UPDATE ON TABLE realtime.messages TO authenticated;
GRANT SELECT,INSERT,UPDATE ON TABLE realtime.messages TO service_role;


--
-- Name: TABLE schema_migrations; Type: ACL; Schema: realtime; Owner: supabase_admin
--

GRANT ALL ON TABLE realtime.schema_migrations TO postgres;
GRANT ALL ON TABLE realtime.schema_migrations TO dashboard_user;


--
-- Name: TABLE subscription; Type: ACL; Schema: realtime; Owner: supabase_realtime_admin
--

GRANT ALL ON TABLE realtime.subscription TO postgres;
GRANT ALL ON TABLE realtime.subscription TO dashboard_user;
GRANT SELECT ON TABLE realtime.subscription TO anon;
GRANT SELECT ON TABLE realtime.subscription TO authenticated;
GRANT SELECT ON TABLE realtime.subscription TO service_role;


--
-- Name: SEQUENCE subscription_id_seq; Type: ACL; Schema: realtime; Owner: supabase_realtime_admin
--

GRANT ALL ON SEQUENCE realtime.subscription_id_seq TO postgres;
GRANT ALL ON SEQUENCE realtime.subscription_id_seq TO dashboard_user;
GRANT USAGE ON SEQUENCE realtime.subscription_id_seq TO anon;
GRANT USAGE ON SEQUENCE realtime.subscription_id_seq TO authenticated;
GRANT USAGE ON SEQUENCE realtime.subscription_id_seq TO service_role;


--
-- Name: TABLE buckets; Type: ACL; Schema: storage; Owner: supabase_storage_admin
--

REVOKE ALL ON TABLE storage.buckets FROM supabase_storage_admin;
GRANT ALL ON TABLE storage.buckets TO supabase_storage_admin WITH GRANT OPTION;
GRANT ALL ON TABLE storage.buckets TO service_role;
GRANT ALL ON TABLE storage.buckets TO authenticated;
GRANT ALL ON TABLE storage.buckets TO anon;
GRANT ALL ON TABLE storage.buckets TO postgres WITH GRANT OPTION;


--
-- Name: TABLE buckets_analytics; Type: ACL; Schema: storage; Owner: supabase_storage_admin
--

GRANT ALL ON TABLE storage.buckets_analytics TO service_role;
GRANT ALL ON TABLE storage.buckets_analytics TO authenticated;
GRANT ALL ON TABLE storage.buckets_analytics TO anon;


--
-- Name: TABLE buckets_vectors; Type: ACL; Schema: storage; Owner: supabase_storage_admin
--

GRANT SELECT ON TABLE storage.buckets_vectors TO service_role;
GRANT SELECT ON TABLE storage.buckets_vectors TO authenticated;
GRANT SELECT ON TABLE storage.buckets_vectors TO anon;


--
-- Name: TABLE objects; Type: ACL; Schema: storage; Owner: supabase_storage_admin
--

REVOKE ALL ON TABLE storage.objects FROM supabase_storage_admin;
GRANT ALL ON TABLE storage.objects TO supabase_storage_admin WITH GRANT OPTION;
GRANT ALL ON TABLE storage.objects TO service_role;
GRANT ALL ON TABLE storage.objects TO authenticated;
GRANT ALL ON TABLE storage.objects TO anon;
GRANT ALL ON TABLE storage.objects TO postgres WITH GRANT OPTION;


--
-- Name: TABLE s3_multipart_uploads; Type: ACL; Schema: storage; Owner: supabase_storage_admin
--

GRANT ALL ON TABLE storage.s3_multipart_uploads TO service_role;
GRANT SELECT ON TABLE storage.s3_multipart_uploads TO authenticated;
GRANT SELECT ON TABLE storage.s3_multipart_uploads TO anon;


--
-- Name: TABLE s3_multipart_uploads_parts; Type: ACL; Schema: storage; Owner: supabase_storage_admin
--

GRANT ALL ON TABLE storage.s3_multipart_uploads_parts TO service_role;
GRANT SELECT ON TABLE storage.s3_multipart_uploads_parts TO authenticated;
GRANT SELECT ON TABLE storage.s3_multipart_uploads_parts TO anon;


--
-- Name: TABLE vector_indexes; Type: ACL; Schema: storage; Owner: supabase_storage_admin
--

GRANT SELECT ON TABLE storage.vector_indexes TO service_role;
GRANT SELECT ON TABLE storage.vector_indexes TO authenticated;
GRANT SELECT ON TABLE storage.vector_indexes TO anon;


--
-- Name: TABLE secrets; Type: ACL; Schema: vault; Owner: supabase_admin
--

GRANT SELECT,REFERENCES,DELETE,TRUNCATE ON TABLE vault.secrets TO postgres WITH GRANT OPTION;
GRANT SELECT,DELETE ON TABLE vault.secrets TO service_role;


--
-- Name: TABLE decrypted_secrets; Type: ACL; Schema: vault; Owner: supabase_admin
--

GRANT SELECT,REFERENCES,DELETE,TRUNCATE ON TABLE vault.decrypted_secrets TO postgres WITH GRANT OPTION;
GRANT SELECT,DELETE ON TABLE vault.decrypted_secrets TO service_role;


--
-- Name: DEFAULT PRIVILEGES FOR SEQUENCES; Type: DEFAULT ACL; Schema: auth; Owner: supabase_auth_admin
--

ALTER DEFAULT PRIVILEGES FOR ROLE supabase_auth_admin IN SCHEMA auth GRANT ALL ON SEQUENCES TO postgres;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_auth_admin IN SCHEMA auth GRANT ALL ON SEQUENCES TO dashboard_user;


--
-- Name: DEFAULT PRIVILEGES FOR FUNCTIONS; Type: DEFAULT ACL; Schema: auth; Owner: supabase_auth_admin
--

ALTER DEFAULT PRIVILEGES FOR ROLE supabase_auth_admin IN SCHEMA auth GRANT ALL ON FUNCTIONS TO postgres;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_auth_admin IN SCHEMA auth GRANT ALL ON FUNCTIONS TO dashboard_user;


--
-- Name: DEFAULT PRIVILEGES FOR TABLES; Type: DEFAULT ACL; Schema: auth; Owner: supabase_auth_admin
--

ALTER DEFAULT PRIVILEGES FOR ROLE supabase_auth_admin IN SCHEMA auth GRANT ALL ON TABLES TO postgres;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_auth_admin IN SCHEMA auth GRANT ALL ON TABLES TO dashboard_user;


--
-- Name: DEFAULT PRIVILEGES FOR SEQUENCES; Type: DEFAULT ACL; Schema: extensions; Owner: supabase_admin
--

ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA extensions GRANT ALL ON SEQUENCES TO postgres WITH GRANT OPTION;


--
-- Name: DEFAULT PRIVILEGES FOR FUNCTIONS; Type: DEFAULT ACL; Schema: extensions; Owner: supabase_admin
--

ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA extensions GRANT ALL ON FUNCTIONS TO postgres WITH GRANT OPTION;


--
-- Name: DEFAULT PRIVILEGES FOR TABLES; Type: DEFAULT ACL; Schema: extensions; Owner: supabase_admin
--

ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA extensions GRANT ALL ON TABLES TO postgres WITH GRANT OPTION;


--
-- Name: DEFAULT PRIVILEGES FOR SEQUENCES; Type: DEFAULT ACL; Schema: graphql; Owner: supabase_admin
--

ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql GRANT ALL ON SEQUENCES TO postgres;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql GRANT ALL ON SEQUENCES TO anon;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql GRANT ALL ON SEQUENCES TO authenticated;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql GRANT ALL ON SEQUENCES TO service_role;


--
-- Name: DEFAULT PRIVILEGES FOR FUNCTIONS; Type: DEFAULT ACL; Schema: graphql; Owner: supabase_admin
--

ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql GRANT ALL ON FUNCTIONS TO postgres;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql GRANT ALL ON FUNCTIONS TO anon;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql GRANT ALL ON FUNCTIONS TO authenticated;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql GRANT ALL ON FUNCTIONS TO service_role;


--
-- Name: DEFAULT PRIVILEGES FOR TABLES; Type: DEFAULT ACL; Schema: graphql; Owner: supabase_admin
--

ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql GRANT ALL ON TABLES TO postgres;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql GRANT ALL ON TABLES TO anon;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql GRANT ALL ON TABLES TO authenticated;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql GRANT ALL ON TABLES TO service_role;


--
-- Name: DEFAULT PRIVILEGES FOR SEQUENCES; Type: DEFAULT ACL; Schema: graphql_public; Owner: supabase_admin
--

ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql_public GRANT ALL ON SEQUENCES TO postgres;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql_public GRANT ALL ON SEQUENCES TO anon;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql_public GRANT ALL ON SEQUENCES TO authenticated;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql_public GRANT ALL ON SEQUENCES TO service_role;


--
-- Name: DEFAULT PRIVILEGES FOR FUNCTIONS; Type: DEFAULT ACL; Schema: graphql_public; Owner: supabase_admin
--

ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql_public GRANT ALL ON FUNCTIONS TO postgres;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql_public GRANT ALL ON FUNCTIONS TO anon;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql_public GRANT ALL ON FUNCTIONS TO authenticated;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql_public GRANT ALL ON FUNCTIONS TO service_role;


--
-- Name: DEFAULT PRIVILEGES FOR TABLES; Type: DEFAULT ACL; Schema: graphql_public; Owner: supabase_admin
--

ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql_public GRANT ALL ON TABLES TO postgres;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql_public GRANT ALL ON TABLES TO anon;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql_public GRANT ALL ON TABLES TO authenticated;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA graphql_public GRANT ALL ON TABLES TO service_role;


--
-- Name: DEFAULT PRIVILEGES FOR SEQUENCES; Type: DEFAULT ACL; Schema: realtime; Owner: supabase_admin
--

ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA realtime GRANT ALL ON SEQUENCES TO postgres;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA realtime GRANT ALL ON SEQUENCES TO dashboard_user;


--
-- Name: DEFAULT PRIVILEGES FOR FUNCTIONS; Type: DEFAULT ACL; Schema: realtime; Owner: supabase_admin
--

ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA realtime GRANT ALL ON FUNCTIONS TO postgres;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA realtime GRANT ALL ON FUNCTIONS TO dashboard_user;


--
-- Name: DEFAULT PRIVILEGES FOR TABLES; Type: DEFAULT ACL; Schema: realtime; Owner: supabase_admin
--

ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA realtime GRANT ALL ON TABLES TO postgres;
ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA realtime GRANT ALL ON TABLES TO dashboard_user;


--
-- Name: DEFAULT PRIVILEGES FOR SEQUENCES; Type: DEFAULT ACL; Schema: storage; Owner: postgres
--

ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA storage GRANT ALL ON SEQUENCES TO postgres;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA storage GRANT ALL ON SEQUENCES TO anon;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA storage GRANT ALL ON SEQUENCES TO authenticated;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA storage GRANT ALL ON SEQUENCES TO service_role;


--
-- Name: DEFAULT PRIVILEGES FOR FUNCTIONS; Type: DEFAULT ACL; Schema: storage; Owner: postgres
--

ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA storage GRANT ALL ON FUNCTIONS TO postgres;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA storage GRANT ALL ON FUNCTIONS TO anon;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA storage GRANT ALL ON FUNCTIONS TO authenticated;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA storage GRANT ALL ON FUNCTIONS TO service_role;


--
-- Name: DEFAULT PRIVILEGES FOR TABLES; Type: DEFAULT ACL; Schema: storage; Owner: postgres
--

ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA storage GRANT ALL ON TABLES TO postgres;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA storage GRANT ALL ON TABLES TO anon;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA storage GRANT ALL ON TABLES TO authenticated;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA storage GRANT ALL ON TABLES TO service_role;


--
-- Name: issue_graphql_placeholder; Type: EVENT TRIGGER; Schema: -; Owner: supabase_admin
--

CREATE EVENT TRIGGER issue_graphql_placeholder ON sql_drop
         WHEN TAG IN ('DROP EXTENSION')
   EXECUTE FUNCTION extensions.set_graphql_placeholder();


ALTER EVENT TRIGGER issue_graphql_placeholder OWNER TO supabase_admin;

--
-- Name: issue_pg_cron_access; Type: EVENT TRIGGER; Schema: -; Owner: supabase_admin
--

CREATE EVENT TRIGGER issue_pg_cron_access ON ddl_command_end
         WHEN TAG IN ('CREATE EXTENSION')
   EXECUTE FUNCTION extensions.grant_pg_cron_access();


ALTER EVENT TRIGGER issue_pg_cron_access OWNER TO supabase_admin;

--
-- Name: issue_pg_graphql_access; Type: EVENT TRIGGER; Schema: -; Owner: supabase_admin
--

CREATE EVENT TRIGGER issue_pg_graphql_access ON ddl_command_end
         WHEN TAG IN ('CREATE EXTENSION')
   EXECUTE FUNCTION extensions.grant_pg_graphql_access();


ALTER EVENT TRIGGER issue_pg_graphql_access OWNER TO supabase_admin;

--
-- Name: issue_pg_net_access; Type: EVENT TRIGGER; Schema: -; Owner: supabase_admin
--

CREATE EVENT TRIGGER issue_pg_net_access ON ddl_command_end
         WHEN TAG IN ('CREATE EXTENSION')
   EXECUTE FUNCTION extensions.grant_pg_net_access();


ALTER EVENT TRIGGER issue_pg_net_access OWNER TO supabase_admin;

--
-- Name: pgrst_ddl_watch; Type: EVENT TRIGGER; Schema: -; Owner: supabase_admin
--

CREATE EVENT TRIGGER pgrst_ddl_watch ON ddl_command_end
   EXECUTE FUNCTION extensions.pgrst_ddl_watch();


ALTER EVENT TRIGGER pgrst_ddl_watch OWNER TO supabase_admin;

--
-- Name: pgrst_drop_watch; Type: EVENT TRIGGER; Schema: -; Owner: supabase_admin
--

CREATE EVENT TRIGGER pgrst_drop_watch ON sql_drop
   EXECUTE FUNCTION extensions.pgrst_drop_watch();


ALTER EVENT TRIGGER pgrst_drop_watch OWNER TO supabase_admin;

--
-- PostgreSQL database dump complete
--

\unrestrict WbfpfS3yR6G3eW0YwT9MCJZDVAnuaASfi1HK9nfhjTKEcJ9mPvcBuW155WwPQyK

