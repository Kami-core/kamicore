-- KAMICORE_INSTALL_PRE_DATA_BEGIN
--
-- PostgreSQL database dump
--

\restrict iYR8qTSkQUH8nTrHEEQTzFHnyV3BeqSeCgaHShwdpPWLm8ovltnacZ0Ri2vCrdz

-- Dumped from database version 18.6 (Debian 18.6-1.pgdg12+2)
-- Dumped by pg_dump version 18.6 (Debian 18.6-1.pgdg12+2)

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
-- Name: public; Type: SCHEMA; Schema: -; Owner: -
--

-- *not* creating schema, since initdb creates it


--
-- Name: SCHEMA public; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON SCHEMA public IS '';


--
-- Name: citext; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS citext WITH SCHEMA public;


--
-- Name: EXTENSION citext; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION citext IS 'data type for case-insensitive character strings';


--
-- Name: pg_trgm; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public;


--
-- Name: EXTENSION pg_trgm; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION pg_trgm IS 'text similarity measurement and index searching based on trigrams';


--
-- Name: status; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.status AS ENUM (
    'draft',
    'online',
    'offline'
);


--
-- Name: item_texts_tsv_refresh_row(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.item_texts_tsv_refresh_row() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_cfg text;
    v_weight char(1);
BEGIN
    -- Search weight belongs to the field attachment in the item's content type.
    SELECT COALESCE(
        NULLIF(ct.schema->'fields'->f.system_name->>'search_weight', ''),
        'D'
    )
    INTO v_weight
    FROM fields f
    JOIN content_items ci ON ci.item_id=NEW.item_id
    JOIN content_types ct ON ct.ct_id=ci.ct_id
    WHERE f.field_id=NEW.field_id;

    v_weight := COALESCE(v_weight, 'D');
    IF v_weight NOT IN ('A', 'B', 'C', 'D') THEN
        v_weight := 'D';
    END IF;

    IF NEW.lang_code IS NULL THEN
        v_cfg := 'simple';
    ELSE
        SELECT COALESCE(l.cfg_name, 'simple')
        INTO v_cfg
        FROM languages l
        WHERE l.lang_code=NEW.lang_code;

        v_cfg := COALESCE(v_cfg, 'simple');
    END IF;

    NEW.tsv := setweight(
        to_tsvector(v_cfg::regconfig, COALESCE(NEW.value, '')),
        v_weight::"char"
    );

    RETURN NEW;
END;
$$;


--
-- Name: item_texts_tsv_refresh_stmt(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.item_texts_tsv_refresh_stmt() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    /*
      Rules:
      - lang_code IS NULL  -> always 'simple'
      - lang_code NOT NULL -> languages.cfg_name (already guaranteed fallback to 'simple')
      - all rows in item_texts are indexed (tsv is always maintained)
    */

    -- A) Not translatable: always simple
    UPDATE item_texts it
    SET tsv = to_tsvector('simple'::regconfig, COALESCE(it.item_value, ''))
    FROM (SELECT DISTINCT id FROM new_rows) n
    WHERE it.id = n.id
      AND it.lang_code IS NULL;

    -- B) Translatable: use languages.cfg_name (fallback to simple if language row missing)
    UPDATE item_texts it
    SET tsv = to_tsvector(
                COALESCE(l.cfg_name, 'simple')::regconfig,
                COALESCE(it.item_value, '')
              )
    FROM languages l
    JOIN (SELECT DISTINCT id FROM new_rows) n ON true
    WHERE it.id = n.id
      AND it.lang_code IS NOT NULL
      AND l.lang_code = it.lang_code;

    -- C) Safety net: lang_code set but languages row is missing => simple
    UPDATE item_texts it
    SET tsv = to_tsvector('simple'::regconfig, COALESCE(it.item_value, ''))
    FROM (SELECT DISTINCT id FROM new_rows) n
    WHERE it.id = n.id
      AND it.lang_code IS NOT NULL
      AND NOT EXISTS (SELECT 1 FROM languages l WHERE l.lang_code = it.lang_code);

    RETURN NULL;
END;
$$;


--
-- Name: languages_cfg_name_biud(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.languages_cfg_name_biud() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    -- If cfg_name omitted OR lang_code changed OR cfg_name set to empty -> auto pick
    IF NEW.cfg_name IS NULL OR NEW.cfg_name = ''
       OR TG_OP = 'INSERT'
       OR (TG_OP = 'UPDATE' AND NEW.lang_code IS DISTINCT FROM OLD.lang_code) THEN
        NEW.cfg_name := languages_pick_ts_config(NEW.lang_code);
    END IF;

    -- Hard guarantee: fallback to simple
    IF NEW.cfg_name IS NULL OR NEW.cfg_name = '' THEN
        NEW.cfg_name := 'simple';
    END IF;

    RETURN NEW;
END;
$$;


--
-- Name: languages_pick_ts_config(text); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.languages_pick_ts_config(p_lang_code text) RETURNS text
    LANGUAGE plpgsql STABLE
    AS $$
DECLARE
    v_code text;
    v_try  text;
BEGIN
    v_code := lower(trim(p_lang_code));
    v_code := split_part(v_code, '-', 1);
    v_code := split_part(v_code, '_', 1);

    -- If someone passes already-a-config name, allow it (but validate existence)
    IF EXISTS (SELECT 1 FROM pg_ts_config WHERE cfgname = v_code) THEN
        RETURN v_code;
    END IF;

    -- Your language list mapping to stock PostgreSQL configs
    v_try := CASE v_code
        WHEN 'ar' THEN 'arabic'
        WHEN 'bg' THEN 'simple'      -- no standard 'bulgarian' config
        WHEN 'cs' THEN 'simple'      -- no standard 'czech' config
        WHEN 'da' THEN 'danish'
        WHEN 'de' THEN 'german'
        WHEN 'el' THEN 'greek'
        WHEN 'en' THEN 'english'
        WHEN 'es' THEN 'spanish'
        WHEN 'fi' THEN 'finnish'
        WHEN 'fr' THEN 'french'
        WHEN 'he' THEN 'hebrew'
        WHEN 'hi' THEN 'simple'      -- no standard 'hindi' config
        WHEN 'hr' THEN 'simple'      -- no standard 'croatian' config
        WHEN 'hu' THEN 'hungarian'
        WHEN 'it' THEN 'italian'
        WHEN 'ja' THEN 'simple'      -- no standard 'japanese' config
        WHEN 'ko' THEN 'simple'      -- no standard 'korean' config
        WHEN 'nl' THEN 'dutch'
        WHEN 'no' THEN 'norwegian'
        WHEN 'pl' THEN 'polish'
        WHEN 'pt' THEN 'portuguese'
        WHEN 'ro' THEN 'romanian'
        WHEN 'ru' THEN 'russian'
        WHEN 'sk' THEN 'simple'      -- no standard 'slovak' config
        WHEN 'sl' THEN 'simple'      -- no standard 'slovenian' config
        WHEN 'sr' THEN 'simple'      -- no standard 'serbian' config
        WHEN 'sv' THEN 'swedish'
        WHEN 'tr' THEN 'turkish'
        WHEN 'uk' THEN 'simple'      -- no standard 'ukrainian' config in core
        WHEN 'zh' THEN 'simple'      -- no standard 'chinese' config
        ELSE 'simple'
    END;

    -- Validate config exists in this конкретній інсталяції, else fallback to simple
    IF v_try IS NOT NULL
       AND EXISTS (SELECT 1 FROM pg_ts_config WHERE cfgname = v_try) THEN
        RETURN v_try;
    END IF;

    RETURN 'simple';
END;
$$;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: api_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.api_tokens (
    token_id bigint NOT NULL,
    user_id integer NOT NULL,
    name text NOT NULL,
    token_hash character(64) NOT NULL,
    token_hint character varying(12) NOT NULL,
    restrictions jsonb DEFAULT '{}'::jsonb NOT NULL,
    is_enabled boolean DEFAULT true NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    expires_at timestamp without time zone,
    last_used_at timestamp without time zone,
    revoked_at timestamp without time zone,
    CONSTRAINT api_tokens_name_check CHECK ((btrim(name) <> ''::text))
);


--
-- Name: api_tokens_token_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.api_tokens ALTER COLUMN token_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.api_tokens_token_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: content_acl; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.content_acl (
    content_acl_id bigint NOT NULL,
    usergroup_id integer NOT NULL,
    ct_id integer NOT NULL,
    handler text NOT NULL,
    created_at timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT content_acl_handler_check CHECK ((btrim(handler) <> ''::text))
);


--
-- Name: content_acl_content_acl_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.content_acl ALTER COLUMN content_acl_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.content_acl_content_acl_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: content_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.content_items (
    item_id bigint NOT NULL,
    item_uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    ct_id integer NOT NULL,
    author_id integer,
    plugin_id integer,
    item_slug text,
    parent_id integer,
    common_data jsonb,
    created_at timestamp without time zone DEFAULT now() NOT NULL,
    updated_at timestamp without time zone DEFAULT now() NOT NULL,
    domain_scope smallint DEFAULT 0 NOT NULL,
    domains integer[],
    item_settings jsonb
);


--
-- Name: content_items_item_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.content_items_item_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: content_items_item_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.content_items_item_id_seq OWNED BY public.content_items.item_id;


--
-- Name: content_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.content_types (
    ct_id integer NOT NULL,
    uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    author_id integer,
    plugin_id integer,
    parent_id integer,
    system_name text CONSTRAINT content_types_ct_name_not_null NOT NULL,
    schema jsonb,
    created_at timestamp without time zone DEFAULT now() NOT NULL,
    has_slug boolean DEFAULT false NOT NULL,
    default_manager_plugin_id integer,
    manager_plugin_id integer,
    manager_overridden boolean DEFAULT false NOT NULL,
    canonical_viewer_plugin_id integer
);


--
-- Name: content_types_ct_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.content_types_ct_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: content_types_ct_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.content_types_ct_id_seq OWNED BY public.content_types.ct_id;


--
-- Name: core_migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.core_migrations (
    migration_name text NOT NULL,
    checksum text NOT NULL,
    applied_at timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: domain_aliases; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.domain_aliases (
    domain_id integer NOT NULL,
    alias_name text NOT NULL
);


--
-- Name: domains; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.domains (
    domain_id integer NOT NULL,
    domain_name text NOT NULL,
    title text,
    description text,
    domain_config jsonb,
    is_root boolean,
    theme_id integer DEFAULT 0 NOT NULL,
    domain_uuid uuid DEFAULT gen_random_uuid() NOT NULL
);


--
-- Name: domains_domain_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.domains_domain_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: domains_domain_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.domains_domain_id_seq OWNED BY public.domains.domain_id;


--
-- Name: field_options; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.field_options (
    option_id integer NOT NULL,
    option_uuid uuid NOT NULL,
    field_id integer NOT NULL,
    option_title text NOT NULL,
    option_value text,
    parent_id integer
);


--
-- Name: field_options_option_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.field_options_option_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: field_options_option_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.field_options_option_id_seq OWNED BY public.field_options.option_id;


--
-- Name: field_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.field_types (
    type_id integer NOT NULL,
    uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    system_name text CONSTRAINT field_types_type_name_not_null NOT NULL,
    type_settings jsonb,
    parent_id integer DEFAULT 0
);


--
-- Name: field_types_type_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.field_types_type_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: field_types_type_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.field_types_type_id_seq OWNED BY public.field_types.type_id;


--
-- Name: fields; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.fields (
    field_id integer NOT NULL,
    uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    type_id integer NOT NULL,
    system_name text CONSTRAINT fields_field_name_not_null NOT NULL,
    field_settings jsonb
);


--
-- Name: fields_field_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.fields_field_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: fields_field_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.fields_field_id_seq OWNED BY public.fields.field_id;


--
-- Name: global_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.global_settings (
    varname text NOT NULL,
    value text NOT NULL,
    settings bytea
);


--
-- Name: item_bools; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.item_bools (
    id bigint NOT NULL,
    item_id bigint NOT NULL,
    field_id bigint NOT NULL,
    value boolean NOT NULL
);


--
-- Name: item_bools_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.item_bools_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: item_bools_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.item_bools_id_seq OWNED BY public.item_bools.id;


--
-- Name: item_dates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.item_dates (
    id bigint NOT NULL,
    item_id bigint NOT NULL,
    field_id bigint NOT NULL,
    value timestamp with time zone
);


--
-- Name: item_dates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.item_dates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: item_dates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.item_dates_id_seq OWNED BY public.item_dates.id;


--
-- Name: item_nums; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.item_nums (
    id bigint NOT NULL,
    item_id bigint NOT NULL,
    field_id bigint NOT NULL,
    value numeric
);


--
-- Name: item_nums_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.item_nums_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: item_nums_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.item_nums_id_seq OWNED BY public.item_nums.id;


--
-- Name: item_relations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.item_relations (
    item_id bigint NOT NULL,
    related_id bigint NOT NULL
);


--
-- Name: item_texts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.item_texts (
    id bigint NOT NULL,
    item_id bigint NOT NULL,
    field_id integer NOT NULL,
    lang_code text,
    value text,
    tsv tsvector
);


--
-- Name: item_texts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.item_texts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: item_texts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.item_texts_id_seq OWNED BY public.item_texts.id;


--
-- Name: languages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.languages (
    lang_code text NOT NULL,
    lang_name text NOT NULL,
    is_active boolean DEFAULT true,
    cfg_name text
);


--
-- Name: logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.logs (
    rec_id bigint NOT NULL,
    log_level text NOT NULL,
    log_message text NOT NULL,
    log_context jsonb,
    created_at timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: logs_rec_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.logs_rec_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: logs_rec_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.logs_rec_id_seq OWNED BY public.logs.rec_id;


--
-- Name: media; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.media (
    media_id integer NOT NULL,
    file_path text NOT NULL,
    uploaded_by integer,
    domain_id integer,
    content_type text,
    created_at timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: media_acl; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.media_acl (
    domain_id integer NOT NULL,
    usergroup_id integer NOT NULL,
    mime_group_id integer NOT NULL,
    can_view boolean DEFAULT false NOT NULL,
    can_upload boolean DEFAULT false NOT NULL,
    can_delete boolean DEFAULT false NOT NULL,
    created_at timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: media_media_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.media_media_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: media_media_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.media_media_id_seq OWNED BY public.media.media_id;


--
-- Name: mime_group_mimes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mime_group_mimes (
    mime_group_id integer NOT NULL,
    mime text NOT NULL,
    ext text,
    is_enabled boolean DEFAULT true NOT NULL,
    created_at timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: mime_groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mime_groups (
    mime_group_id integer NOT NULL,
    mime_group_uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    mime_group_code text NOT NULL,
    title text DEFAULT ''::text NOT NULL,
    description text,
    is_system boolean DEFAULT true NOT NULL,
    is_enabled boolean DEFAULT true NOT NULL,
    created_at timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: mime_groups_mime_group_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.mime_groups ALTER COLUMN mime_group_id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.mime_groups_mime_group_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: notification_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE UNLOGGED TABLE public.notification_messages (
    notification_id bigint NOT NULL,
    context_id text NOT NULL,
    user_id bigint,
    text text NOT NULL,
    style character varying(16) DEFAULT 'default'::character varying NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    expires_at timestamp with time zone,
    CONSTRAINT notification_messages_style_check CHECK (((style)::text = ANY (ARRAY[('default'::character varying)::text, ('success'::character varying)::text, ('alert'::character varying)::text, ('danger'::character varying)::text])))
)
WITH (autovacuum_vacuum_scale_factor='0.02', autovacuum_vacuum_threshold='200', autovacuum_analyze_scale_factor='0.05', autovacuum_analyze_threshold='200');


--
-- Name: notification_messages_notification_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.notification_messages ALTER COLUMN notification_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.notification_messages_notification_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: page_acl; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.page_acl (
    page_id integer NOT NULL,
    usergroup_id integer NOT NULL,
    created_at timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: pages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pages (
    page_id integer NOT NULL,
    domain_id integer,
    uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    system_name text CONSTRAINT pages_page_name_not_null NOT NULL,
    page_slug text NOT NULL,
    page_settings jsonb,
    page_plugins jsonb,
    layout_id integer DEFAULT 0 NOT NULL,
    parent_id integer,
    CONSTRAINT pages_parent_id_not_self CHECK (((parent_id IS NULL) OR (parent_id <> page_id)))
);


--
-- Name: pages_page_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pages_page_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pages_page_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pages_page_id_seq OWNED BY public.pages.page_id;


--
-- Name: pgm_recipes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pgm_recipes (
    recipe_id integer NOT NULL,
    recipe_uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    recipe_key text NOT NULL,
    name text NOT NULL,
    description text,
    payload jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp without time zone DEFAULT now() NOT NULL,
    updated_at timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: pgm_recipes_recipe_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pgm_recipes_recipe_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pgm_recipes_recipe_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pgm_recipes_recipe_id_seq OWNED BY public.pgm_recipes.recipe_id;


--
-- Name: plugin_acl; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.plugin_acl (
    plugin_acl_id bigint NOT NULL,
    usergroup_id integer NOT NULL,
    plugin_id integer NOT NULL,
    handler text NOT NULL,
    created_at timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT plugin_acl_handler_check CHECK ((btrim(handler) <> ''::text))
);


--
-- Name: plugin_acl_plugin_acl_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.plugin_acl ALTER COLUMN plugin_acl_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.plugin_acl_plugin_acl_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: plugin_domains; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.plugin_domains (
    plugin_id integer NOT NULL,
    domain_id integer NOT NULL,
    local_settings jsonb
);


--
-- Name: plugin_endpoints; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.plugin_endpoints (
    endpoint_id integer NOT NULL,
    plugin_id integer NOT NULL,
    endpoint character varying(100) NOT NULL,
    route_method character varying(100) NOT NULL,
    CONSTRAINT plugin_endpoints_endpoint_format_check CHECK (((endpoint)::text ~ '^[a-z][a-z0-9_-]*$'::text)),
    CONSTRAINT plugin_endpoints_route_method_format_check CHECK (((route_method)::text ~ '^[A-Za-z_][A-Za-z0-9_]*$'::text))
);


--
-- Name: plugin_endpoints_endpoint_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.plugin_endpoints ALTER COLUMN endpoint_id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.plugin_endpoints_endpoint_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: plugin_migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.plugin_migrations (
    plugin_id integer NOT NULL,
    migration_name text NOT NULL,
    checksum text NOT NULL,
    applied_at timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: plugins; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.plugins (
    plugin_id integer NOT NULL,
    uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    system_name text CONSTRAINT plugins_plugin_name_not_null NOT NULL,
    plugin_prefix text,
    settings jsonb,
    context_vars jsonb,
    default_language text,
    is_active boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT now() NOT NULL,
    plugin_version text,
    plugin_author text,
    global_settings jsonb,
    default_settings jsonb,
    config jsonb
);


--
-- Name: plugins_plugin_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.plugins_plugin_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: plugins_plugin_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.plugins_plugin_id_seq OWNED BY public.plugins.plugin_id;


--
-- Name: pm_setup_history; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pm_setup_history (
    setup_id integer NOT NULL,
    plugin_id integer,
    plugin_system_name text NOT NULL,
    domain_id integer,
    action text NOT NULL,
    status text NOT NULL,
    preset_name text,
    config jsonb DEFAULT '{}'::jsonb NOT NULL,
    error text,
    created_at timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT pm_setup_history_action_check CHECK ((action = ANY (ARRAY['install'::text, 'update'::text, 'setup'::text, 'remove_from_site'::text, 'uninstall'::text]))),
    CONSTRAINT pm_setup_history_status_check CHECK ((status = ANY (ARRAY['success'::text, 'failed'::text])))
);


--
-- Name: pm_setup_history_setup_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pm_setup_history_setup_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pm_setup_history_setup_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pm_setup_history_setup_id_seq OWNED BY public.pm_setup_history.setup_id;


--
-- Name: pm_setup_resources; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pm_setup_resources (
    setup_resource_id integer NOT NULL,
    setup_id integer NOT NULL,
    resource_key text NOT NULL,
    resource_type text NOT NULL,
    resource_id bigint,
    resource_uuid uuid,
    ownership text NOT NULL,
    recipe_id integer,
    recipe_snapshot jsonb,
    config jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp without time zone DEFAULT now() NOT NULL,
    updated_at timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: pm_setup_resources_setup_resource_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pm_setup_resources_setup_resource_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pm_setup_resources_setup_resource_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pm_setup_resources_setup_resource_id_seq OWNED BY public.pm_setup_resources.setup_resource_id;


--
-- Name: secrets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.secrets (
    secret_id integer NOT NULL,
    namespace character varying(100) NOT NULL,
    secret_name character varying(150) NOT NULL,
    domain_id integer,
    encrypted_value bytea NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: secrets_secret_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.secrets ALTER COLUMN secret_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.secrets_secret_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    domain_id bigint NOT NULL,
    session_id text NOT NULL,
    user_id bigint DEFAULT 0,
    created_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now(),
    is_persistent boolean DEFAULT false NOT NULL,
    ua_hash text NOT NULL,
    data jsonb DEFAULT '{}'::jsonb NOT NULL
);


--
-- Name: theme_layouts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.theme_layouts (
    layout_id integer NOT NULL,
    uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    theme_id smallint NOT NULL,
    system_name text NOT NULL,
    layout_filename text NOT NULL,
    wrappers jsonb
);


--
-- Name: theme_layouts_layout_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.theme_layouts_layout_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: theme_layouts_layout_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.theme_layouts_layout_id_seq OWNED BY public.theme_layouts.layout_id;


--
-- Name: themes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.themes (
    theme_id integer NOT NULL,
    uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    system_name text CONSTRAINT themes_theme_name_not_null NOT NULL,
    created_at timestamp without time zone DEFAULT now() NOT NULL,
    theme_settings jsonb,
    theme_version text
);


--
-- Name: themes_theme_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.themes_theme_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: themes_theme_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.themes_theme_id_seq OWNED BY public.themes.theme_id;


--
-- Name: tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tokens (
    token_id integer NOT NULL,
    user_id integer,
    method text NOT NULL,
    token text NOT NULL,
    expires_at timestamp without time zone,
    token_data jsonb
);


--
-- Name: tokens_2fa_token_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tokens_2fa_token_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tokens_2fa_token_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tokens_2fa_token_id_seq OWNED BY public.tokens.token_id;


--
-- Name: translations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.translations (
    translation_id integer NOT NULL,
    entity_uuid uuid NOT NULL,
    lang_code text NOT NULL,
    translated_data jsonb,
    translation_status text DEFAULT 'draft'::text,
    is_default boolean DEFAULT false NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: translations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.translations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: translations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.translations_id_seq OWNED BY public.translations.translation_id;


--
-- Name: user_auth_identities; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_auth_identities (
    id integer NOT NULL,
    user_id integer NOT NULL,
    provider character varying(32) NOT NULL,
    provider_user_id text NOT NULL,
    provider_email text,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    last_used_at timestamp with time zone
);


--
-- Name: user_auth_identities_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.user_auth_identities ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.user_auth_identities_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: user_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_profiles (
    user_id integer NOT NULL,
    profile_data jsonb
);


--
-- Name: usergroups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.usergroups (
    usergroup_id integer NOT NULL,
    uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    system_name text NOT NULL,
    is_system boolean DEFAULT false NOT NULL,
    has_api boolean DEFAULT false NOT NULL
);


--
-- Name: usergroups_usergroup_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.usergroups_usergroup_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: usergroups_usergroup_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.usergroups_usergroup_id_seq OWNED BY public.usergroups.usergroup_id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    user_id integer NOT NULL,
    user_uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    username public.citext NOT NULL,
    email text,
    password_hash text,
    created_at timestamp without time zone DEFAULT now(),
    last_login timestamp without time zone,
    login_data jsonb,
    usergroup_id integer NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    email_verified_at timestamp without time zone
);


--
-- Name: users_user_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_user_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_user_id_seq OWNED BY public.users.user_id;


--
-- Name: vc_content_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vc_content_types (
    ct_id integer NOT NULL,
    single_template text,
    list_template text,
    tree_template text
);


--
-- Name: content_items item_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.content_items ALTER COLUMN item_id SET DEFAULT nextval('public.content_items_item_id_seq'::regclass);


--
-- Name: content_types ct_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.content_types ALTER COLUMN ct_id SET DEFAULT nextval('public.content_types_ct_id_seq'::regclass);


--
-- Name: domains domain_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domains ALTER COLUMN domain_id SET DEFAULT nextval('public.domains_domain_id_seq'::regclass);


--
-- Name: field_options option_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.field_options ALTER COLUMN option_id SET DEFAULT nextval('public.field_options_option_id_seq'::regclass);


--
-- Name: field_types type_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.field_types ALTER COLUMN type_id SET DEFAULT nextval('public.field_types_type_id_seq'::regclass);


--
-- Name: fields field_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fields ALTER COLUMN field_id SET DEFAULT nextval('public.fields_field_id_seq'::regclass);


--
-- Name: item_bools id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_bools ALTER COLUMN id SET DEFAULT nextval('public.item_bools_id_seq'::regclass);


--
-- Name: item_dates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_dates ALTER COLUMN id SET DEFAULT nextval('public.item_dates_id_seq'::regclass);


--
-- Name: item_nums id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_nums ALTER COLUMN id SET DEFAULT nextval('public.item_nums_id_seq'::regclass);


--
-- Name: item_texts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_texts ALTER COLUMN id SET DEFAULT nextval('public.item_texts_id_seq'::regclass);


--
-- Name: logs rec_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.logs ALTER COLUMN rec_id SET DEFAULT nextval('public.logs_rec_id_seq'::regclass);


--
-- Name: media media_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media ALTER COLUMN media_id SET DEFAULT nextval('public.media_media_id_seq'::regclass);


--
-- Name: pages page_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pages ALTER COLUMN page_id SET DEFAULT nextval('public.pages_page_id_seq'::regclass);


--
-- Name: pgm_recipes recipe_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pgm_recipes ALTER COLUMN recipe_id SET DEFAULT nextval('public.pgm_recipes_recipe_id_seq'::regclass);


--
-- Name: plugins plugin_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plugins ALTER COLUMN plugin_id SET DEFAULT nextval('public.plugins_plugin_id_seq'::regclass);


--
-- Name: pm_setup_history setup_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pm_setup_history ALTER COLUMN setup_id SET DEFAULT nextval('public.pm_setup_history_setup_id_seq'::regclass);


--
-- Name: pm_setup_resources setup_resource_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pm_setup_resources ALTER COLUMN setup_resource_id SET DEFAULT nextval('public.pm_setup_resources_setup_resource_id_seq'::regclass);


--
-- Name: theme_layouts layout_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.theme_layouts ALTER COLUMN layout_id SET DEFAULT nextval('public.theme_layouts_layout_id_seq'::regclass);


--
-- Name: themes theme_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.themes ALTER COLUMN theme_id SET DEFAULT nextval('public.themes_theme_id_seq'::regclass);


--
-- Name: tokens token_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tokens ALTER COLUMN token_id SET DEFAULT nextval('public.tokens_2fa_token_id_seq'::regclass);


--
-- Name: translations translation_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translations ALTER COLUMN translation_id SET DEFAULT nextval('public.translations_id_seq'::regclass);


--
-- Name: usergroups usergroup_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usergroups ALTER COLUMN usergroup_id SET DEFAULT nextval('public.usergroups_usergroup_id_seq'::regclass);


--
-- Name: users user_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN user_id SET DEFAULT nextval('public.users_user_id_seq'::regclass);


--
-- PostgreSQL database dump complete
--

\unrestrict iYR8qTSkQUH8nTrHEEQTzFHnyV3BeqSeCgaHShwdpPWLm8ovltnacZ0Ri2vCrdz

-- KAMICORE_INSTALL_PRE_DATA_END
-- KAMICORE_INSTALL_POST_DATA_BEGIN
--
-- PostgreSQL database dump
--

\restrict rxCJb4KCefjBXbY1JSKKy4EJx6Feb5FxE042AoypcQzPKmVgSCTldu7SaEPkltC

-- Dumped from database version 18.6 (Debian 18.6-1.pgdg12+2)
-- Dumped by pg_dump version 18.6 (Debian 18.6-1.pgdg12+2)

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

SET default_tablespace = '';

--
-- Name: api_tokens api_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.api_tokens
    ADD CONSTRAINT api_tokens_pkey PRIMARY KEY (token_id);


--
-- Name: api_tokens api_tokens_token_hash_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.api_tokens
    ADD CONSTRAINT api_tokens_token_hash_key UNIQUE (token_hash);


--
-- Name: content_acl content_acl_permission_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.content_acl
    ADD CONSTRAINT content_acl_permission_unique UNIQUE (usergroup_id, ct_id, handler);


--
-- Name: content_acl content_acl_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.content_acl
    ADD CONSTRAINT content_acl_pkey PRIMARY KEY (content_acl_id);


--
-- Name: content_items content_items_item_slug_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.content_items
    ADD CONSTRAINT content_items_item_slug_key UNIQUE (item_slug);


--
-- Name: content_items content_items_item_uuid_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.content_items
    ADD CONSTRAINT content_items_item_uuid_key UNIQUE (item_uuid);


--
-- Name: content_items content_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.content_items
    ADD CONSTRAINT content_items_pkey PRIMARY KEY (item_id);


--
-- Name: content_types content_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.content_types
    ADD CONSTRAINT content_types_pkey PRIMARY KEY (ct_id);


--
-- Name: content_types content_types_system_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.content_types
    ADD CONSTRAINT content_types_system_name_key UNIQUE (system_name);


--
-- Name: content_types content_types_uuid_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.content_types
    ADD CONSTRAINT content_types_uuid_key UNIQUE (uuid);


--
-- Name: core_migrations core_migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.core_migrations
    ADD CONSTRAINT core_migrations_pkey PRIMARY KEY (migration_name);


--
-- Name: domain_aliases domain_aliases_alias_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_aliases
    ADD CONSTRAINT domain_aliases_alias_name_key UNIQUE (alias_name);


--
-- Name: domain_aliases domain_aliases_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_aliases
    ADD CONSTRAINT domain_aliases_pkey PRIMARY KEY (domain_id, alias_name);


--
-- Name: domains domains_domain_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domains
    ADD CONSTRAINT domains_domain_name_key UNIQUE (domain_name);


--
-- Name: domains domains_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domains
    ADD CONSTRAINT domains_pkey PRIMARY KEY (domain_id);


--
-- Name: field_options field_options_option_uuid_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.field_options
    ADD CONSTRAINT field_options_option_uuid_key UNIQUE (option_uuid);


--
-- Name: field_options field_options_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.field_options
    ADD CONSTRAINT field_options_pkey PRIMARY KEY (option_id);


--
-- Name: field_types field_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.field_types
    ADD CONSTRAINT field_types_pkey PRIMARY KEY (type_id);


--
-- Name: field_types field_types_system_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.field_types
    ADD CONSTRAINT field_types_system_name_key UNIQUE (system_name);


--
-- Name: field_types field_types_uuid_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.field_types
    ADD CONSTRAINT field_types_uuid_key UNIQUE (uuid);


--
-- Name: fields fields_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fields
    ADD CONSTRAINT fields_pkey PRIMARY KEY (field_id);


--
-- Name: fields fields_uuid_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fields
    ADD CONSTRAINT fields_uuid_key UNIQUE (uuid);


--
-- Name: global_settings global_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.global_settings
    ADD CONSTRAINT global_settings_pkey PRIMARY KEY (varname);


--
-- Name: item_bools item_bools_item_id_field_id_value_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_bools
    ADD CONSTRAINT item_bools_item_id_field_id_value_key UNIQUE (item_id, field_id, value);


--
-- Name: item_bools item_bools_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_bools
    ADD CONSTRAINT item_bools_pkey PRIMARY KEY (id);


--
-- Name: item_dates item_dates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_dates
    ADD CONSTRAINT item_dates_pkey PRIMARY KEY (id);


--
-- Name: item_nums item_nums_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_nums
    ADD CONSTRAINT item_nums_pkey PRIMARY KEY (id);


--
-- Name: item_relations item_relations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_relations
    ADD CONSTRAINT item_relations_pkey PRIMARY KEY (item_id, related_id);


--
-- Name: item_texts item_texts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_texts
    ADD CONSTRAINT item_texts_pkey PRIMARY KEY (id);


--
-- Name: languages languages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.languages
    ADD CONSTRAINT languages_pkey PRIMARY KEY (lang_code);


--
-- Name: logs logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.logs
    ADD CONSTRAINT logs_pkey PRIMARY KEY (rec_id);


--
-- Name: media_acl media_acl_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media_acl
    ADD CONSTRAINT media_acl_pkey PRIMARY KEY (domain_id, usergroup_id, mime_group_id);


--
-- Name: media media_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media
    ADD CONSTRAINT media_pkey PRIMARY KEY (media_id);


--
-- Name: mime_group_mimes mime_group_mimes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mime_group_mimes
    ADD CONSTRAINT mime_group_mimes_pkey PRIMARY KEY (mime_group_id, mime);


--
-- Name: mime_groups mime_groups_mime_group_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mime_groups
    ADD CONSTRAINT mime_groups_mime_group_code_key UNIQUE (mime_group_code);


--
-- Name: mime_groups mime_groups_mime_group_uuid_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mime_groups
    ADD CONSTRAINT mime_groups_mime_group_uuid_key UNIQUE (mime_group_uuid);


--
-- Name: mime_groups mime_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mime_groups
    ADD CONSTRAINT mime_groups_pkey PRIMARY KEY (mime_group_id);


--
-- Name: notification_messages notification_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_messages
    ADD CONSTRAINT notification_messages_pkey PRIMARY KEY (notification_id);


--
-- Name: page_acl page_acl_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.page_acl
    ADD CONSTRAINT page_acl_pkey PRIMARY KEY (page_id, usergroup_id);


--
-- Name: pages pages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pages
    ADD CONSTRAINT pages_pkey PRIMARY KEY (page_id);


--
-- Name: pages pages_uuid_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pages
    ADD CONSTRAINT pages_uuid_key UNIQUE (uuid);


--
-- Name: pgm_recipes pgm_recipes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pgm_recipes
    ADD CONSTRAINT pgm_recipes_pkey PRIMARY KEY (recipe_id);


--
-- Name: pgm_recipes pgm_recipes_recipe_key_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pgm_recipes
    ADD CONSTRAINT pgm_recipes_recipe_key_key UNIQUE (recipe_key);


--
-- Name: pgm_recipes pgm_recipes_recipe_uuid_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pgm_recipes
    ADD CONSTRAINT pgm_recipes_recipe_uuid_key UNIQUE (recipe_uuid);


--
-- Name: plugin_acl plugin_acl_permission_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plugin_acl
    ADD CONSTRAINT plugin_acl_permission_unique UNIQUE (usergroup_id, plugin_id, handler);


--
-- Name: plugin_acl plugin_acl_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plugin_acl
    ADD CONSTRAINT plugin_acl_pkey PRIMARY KEY (plugin_acl_id);


--
-- Name: plugin_domains plugin_domains_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plugin_domains
    ADD CONSTRAINT plugin_domains_pkey PRIMARY KEY (plugin_id, domain_id);


--
-- Name: plugin_endpoints plugin_endpoints_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plugin_endpoints
    ADD CONSTRAINT plugin_endpoints_pkey PRIMARY KEY (endpoint_id);


--
-- Name: plugin_endpoints plugin_endpoints_plugin_endpoint_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plugin_endpoints
    ADD CONSTRAINT plugin_endpoints_plugin_endpoint_unique UNIQUE (plugin_id, endpoint);


--
-- Name: plugin_migrations plugin_migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plugin_migrations
    ADD CONSTRAINT plugin_migrations_pkey PRIMARY KEY (plugin_id, migration_name);


--
-- Name: plugins plugins_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plugins
    ADD CONSTRAINT plugins_pkey PRIMARY KEY (plugin_id);


--
-- Name: plugins plugins_system_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plugins
    ADD CONSTRAINT plugins_system_name_key UNIQUE (system_name);


--
-- Name: plugins plugins_uuid_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plugins
    ADD CONSTRAINT plugins_uuid_key UNIQUE (uuid);


--
-- Name: pm_setup_history pm_setup_history_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pm_setup_history
    ADD CONSTRAINT pm_setup_history_pkey PRIMARY KEY (setup_id);


--
-- Name: pm_setup_resources pm_setup_resources_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pm_setup_resources
    ADD CONSTRAINT pm_setup_resources_pkey PRIMARY KEY (setup_resource_id);


--
-- Name: pm_setup_resources pm_setup_resources_setup_id_resource_key_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pm_setup_resources
    ADD CONSTRAINT pm_setup_resources_setup_id_resource_key_key UNIQUE (setup_id, resource_key);


--
-- Name: secrets secrets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.secrets
    ADD CONSTRAINT secrets_pkey PRIMARY KEY (secret_id);


--
-- Name: secrets secrets_scope_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.secrets
    ADD CONSTRAINT secrets_scope_unique UNIQUE NULLS NOT DISTINCT (namespace, secret_name, domain_id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (domain_id, session_id);


--
-- Name: theme_layouts theme_layouts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.theme_layouts
    ADD CONSTRAINT theme_layouts_pkey PRIMARY KEY (layout_id);


--
-- Name: theme_layouts theme_layouts_theme_system_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.theme_layouts
    ADD CONSTRAINT theme_layouts_theme_system_name_key UNIQUE (theme_id, system_name);


--
-- Name: theme_layouts theme_layouts_uuid_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.theme_layouts
    ADD CONSTRAINT theme_layouts_uuid_key UNIQUE (uuid);


--
-- Name: themes themes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.themes
    ADD CONSTRAINT themes_pkey PRIMARY KEY (theme_id);


--
-- Name: themes themes_system_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.themes
    ADD CONSTRAINT themes_system_name_key UNIQUE (system_name);


--
-- Name: themes themes_uuid_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.themes
    ADD CONSTRAINT themes_uuid_key UNIQUE (uuid);


--
-- Name: tokens tokens_2fa_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tokens
    ADD CONSTRAINT tokens_2fa_pkey PRIMARY KEY (token_id);


--
-- Name: translations translations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translations
    ADD CONSTRAINT translations_pkey PRIMARY KEY (translation_id);


--
-- Name: user_auth_identities user_auth_identities_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_auth_identities
    ADD CONSTRAINT user_auth_identities_pkey PRIMARY KEY (id);


--
-- Name: user_auth_identities user_auth_identities_provider_uid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_auth_identities
    ADD CONSTRAINT user_auth_identities_provider_uid_unique UNIQUE (provider, provider_user_id);


--
-- Name: user_auth_identities user_auth_identities_user_provider_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_auth_identities
    ADD CONSTRAINT user_auth_identities_user_provider_unique UNIQUE (user_id, provider);


--
-- Name: user_profiles user_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_profiles
    ADD CONSTRAINT user_profiles_pkey PRIMARY KEY (user_id);


--
-- Name: usergroups usergroups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usergroups
    ADD CONSTRAINT usergroups_pkey PRIMARY KEY (usergroup_id);


--
-- Name: usergroups usergroups_system_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usergroups
    ADD CONSTRAINT usergroups_system_name_key UNIQUE (system_name);


--
-- Name: usergroups usergroups_uuid_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usergroups
    ADD CONSTRAINT usergroups_uuid_key UNIQUE (uuid);


--
-- Name: users users_email_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (user_id);


--
-- Name: users users_user_uuid_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_user_uuid_key UNIQUE (user_uuid);


--
-- Name: users users_username_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_username_key UNIQUE (username);


--
-- Name: vc_content_types vc_content_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vc_content_types
    ADD CONSTRAINT vc_content_types_pkey PRIMARY KEY (ct_id);


--
-- Name: api_tokens_user_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX api_tokens_user_idx ON public.api_tokens USING btree (user_id, created_at DESC, token_id DESC);


--
-- Name: content_types_manager_plugin_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX content_types_manager_plugin_id_idx ON public.content_types USING btree (manager_plugin_id);


--
-- Name: idx_content_items_author_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_content_items_author_id ON public.content_items USING btree (author_id);


--
-- Name: idx_content_items_parent_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_content_items_parent_id ON public.content_items USING btree (parent_id);


--
-- Name: idx_content_items_plugin_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_content_items_plugin_id ON public.content_items USING btree (plugin_id);


--
-- Name: idx_content_items_type_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_content_items_type_id ON public.content_items USING btree (ct_id);


--
-- Name: idx_content_types_parent_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_content_types_parent_id ON public.content_types USING btree (parent_id);


--
-- Name: idx_content_types_plugin_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_content_types_plugin_id ON public.content_types USING btree (plugin_id);


--
-- Name: idx_domain_aliases_alias_name; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_domain_aliases_alias_name ON public.domain_aliases USING btree (alias_name);


--
-- Name: idx_domains_domain_name; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_domains_domain_name ON public.domains USING btree (domain_name);


--
-- Name: idx_field_options_field_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_field_options_field_id ON public.field_options USING btree (field_id);


--
-- Name: idx_fields_system_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_fields_system_name ON public.fields USING btree (system_name) WITH (deduplicate_items='false');


--
-- Name: idx_fields_type_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_fields_type_id ON public.fields USING btree (type_id);


--
-- Name: idx_item_bools_field_value_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_item_bools_field_value_item ON public.item_bools USING btree (field_id, value, item_id);


--
-- Name: idx_item_dates_field_value_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_item_dates_field_value_item ON public.item_dates USING btree (field_id, value, item_id);


--
-- Name: idx_item_dates_item_field; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_item_dates_item_field ON public.item_dates USING btree (item_id, field_id);


--
-- Name: idx_item_nums_field_value_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_item_nums_field_value_item ON public.item_nums USING btree (field_id, value, item_id);


--
-- Name: idx_item_nums_item_field; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_item_nums_item_field ON public.item_nums USING btree (item_id, field_id);


--
-- Name: idx_item_texts_field_lang_hash_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_item_texts_field_lang_hash_item ON public.item_texts USING btree (field_id, lang_code, md5(value), item_id);


--
-- Name: idx_item_texts_item_field_lang; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_item_texts_item_field_lang ON public.item_texts USING btree (item_id, field_id, lang_code);


--
-- Name: idx_item_texts_tsv; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_item_texts_tsv ON public.item_texts USING gin (tsv);


--
-- Name: idx_item_texts_value_trgm; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_item_texts_value_trgm ON public.item_texts USING gin (value public.gin_trgm_ops);


--
-- Name: idx_pages_system_name; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pages_system_name ON public.pages USING btree (system_name);


--
-- Name: media_acl_group_domain_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX media_acl_group_domain_idx ON public.media_acl USING btree (usergroup_id, domain_id);


--
-- Name: media_acl_mime_group_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX media_acl_mime_group_idx ON public.media_acl USING btree (mime_group_id);


--
-- Name: mime_group_mimes_enabled_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mime_group_mimes_enabled_idx ON public.mime_group_mimes USING btree (mime_group_id) WHERE (is_enabled = true);


--
-- Name: mime_group_mimes_mime_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mime_group_mimes_mime_idx ON public.mime_group_mimes USING btree (mime);


--
-- Name: mime_groups_enabled_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mime_groups_enabled_idx ON public.mime_groups USING btree (is_enabled);


--
-- Name: notification_messages_expires_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_messages_expires_idx ON public.notification_messages USING btree (expires_at, notification_id) WHERE (expires_at IS NOT NULL);


--
-- Name: notification_messages_recipient_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_messages_recipient_idx ON public.notification_messages USING btree (context_id, user_id, created_at, notification_id);


--
-- Name: page_acl_group_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX page_acl_group_idx ON public.page_acl USING btree (usergroup_id);


--
-- Name: pages_parent_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pages_parent_id_idx ON public.pages USING btree (parent_id);


--
-- Name: plugin_endpoints_endpoint_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX plugin_endpoints_endpoint_idx ON public.plugin_endpoints USING btree (endpoint);


--
-- Name: pm_setup_history_domain_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pm_setup_history_domain_idx ON public.pm_setup_history USING btree (domain_id, created_at DESC);


--
-- Name: pm_setup_history_plugin_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pm_setup_history_plugin_idx ON public.pm_setup_history USING btree (plugin_system_name, created_at DESC);


--
-- Name: pm_setup_resources_resource_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pm_setup_resources_resource_idx ON public.pm_setup_resources USING btree (resource_type, resource_id);


--
-- Name: sessions_user_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_idx ON public.sessions USING btree (user_id);


--
-- Name: tokens_token_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX tokens_token_idx ON public.tokens USING btree (token) WITH (deduplicate_items='false');


--
-- Name: translations_entity_uuid_lang_code_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX translations_entity_uuid_lang_code_idx ON public.translations USING btree (entity_uuid, lang_code) WITH (deduplicate_items='false');


--
-- Name: user_auth_identities_user_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_auth_identities_user_id_idx ON public.user_auth_identities USING btree (user_id);


--
-- Name: users_email_lower_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX users_email_lower_unique ON public.users USING btree (lower(email)) WHERE (email IS NOT NULL);


--
-- Name: users_username_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX users_username_unique ON public.users USING btree (username);


--
-- Name: item_texts trg_item_texts_tsv_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_item_texts_tsv_row BEFORE INSERT OR UPDATE OF value, lang_code, field_id ON public.item_texts FOR EACH ROW EXECUTE FUNCTION public.item_texts_tsv_refresh_row();


--
-- Name: languages trg_languages_cfg_name; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_languages_cfg_name BEFORE INSERT OR UPDATE OF lang_code, cfg_name ON public.languages FOR EACH ROW EXECUTE FUNCTION public.languages_cfg_name_biud();


--
-- Name: api_tokens api_tokens_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.api_tokens
    ADD CONSTRAINT api_tokens_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON DELETE CASCADE;


--
-- Name: content_acl content_acl_ct_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.content_acl
    ADD CONSTRAINT content_acl_ct_id_fkey FOREIGN KEY (ct_id) REFERENCES public.content_types(ct_id) ON DELETE CASCADE;


--
-- Name: content_acl content_acl_usergroup_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.content_acl
    ADD CONSTRAINT content_acl_usergroup_id_fkey FOREIGN KEY (usergroup_id) REFERENCES public.usergroups(usergroup_id) ON DELETE CASCADE;


--
-- Name: content_types content_types_canonical_viewer_plugin_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.content_types
    ADD CONSTRAINT content_types_canonical_viewer_plugin_id_fkey FOREIGN KEY (canonical_viewer_plugin_id) REFERENCES public.plugins(plugin_id) ON DELETE SET NULL;


--
-- Name: content_types content_types_default_manager_plugin_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.content_types
    ADD CONSTRAINT content_types_default_manager_plugin_id_fkey FOREIGN KEY (default_manager_plugin_id) REFERENCES public.plugins(plugin_id) ON DELETE SET NULL;


--
-- Name: content_types content_types_manager_plugin_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.content_types
    ADD CONSTRAINT content_types_manager_plugin_id_fkey FOREIGN KEY (manager_plugin_id) REFERENCES public.plugins(plugin_id) ON DELETE SET NULL;


--
-- Name: domain_aliases domain_aliases_domain_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_aliases
    ADD CONSTRAINT domain_aliases_domain_id_fkey FOREIGN KEY (domain_id) REFERENCES public.domains(domain_id);


--
-- Name: field_options field_options_field_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.field_options
    ADD CONSTRAINT field_options_field_id_fkey FOREIGN KEY (field_id) REFERENCES public.fields(field_id) ON DELETE CASCADE;


--
-- Name: fields fields_type_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fields
    ADD CONSTRAINT fields_type_id_fkey FOREIGN KEY (type_id) REFERENCES public.field_types(type_id) ON DELETE RESTRICT;


--
-- Name: item_bools item_bools_item_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_bools
    ADD CONSTRAINT item_bools_item_id_fkey FOREIGN KEY (item_id) REFERENCES public.content_items(item_id) ON DELETE CASCADE;


--
-- Name: item_dates item_dates_item_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_dates
    ADD CONSTRAINT item_dates_item_id_fkey FOREIGN KEY (item_id) REFERENCES public.content_items(item_id) ON DELETE CASCADE;


--
-- Name: item_texts item_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_texts
    ADD CONSTRAINT item_id_fk FOREIGN KEY (item_id) REFERENCES public.content_items(item_id) ON DELETE CASCADE NOT VALID;


--
-- Name: item_nums item_nums_item_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_nums
    ADD CONSTRAINT item_nums_item_id_fkey FOREIGN KEY (item_id) REFERENCES public.content_items(item_id) ON DELETE CASCADE;


--
-- Name: media_acl media_acl_domain_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media_acl
    ADD CONSTRAINT media_acl_domain_id_fkey FOREIGN KEY (domain_id) REFERENCES public.domains(domain_id) ON DELETE CASCADE;


--
-- Name: media_acl media_acl_mime_group_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media_acl
    ADD CONSTRAINT media_acl_mime_group_id_fkey FOREIGN KEY (mime_group_id) REFERENCES public.mime_groups(mime_group_id) ON DELETE CASCADE;


--
-- Name: media_acl media_acl_usergroup_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media_acl
    ADD CONSTRAINT media_acl_usergroup_id_fkey FOREIGN KEY (usergroup_id) REFERENCES public.usergroups(usergroup_id) ON DELETE CASCADE;


--
-- Name: media media_uploaded_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media
    ADD CONSTRAINT media_uploaded_by_fkey FOREIGN KEY (uploaded_by) REFERENCES public.users(user_id);


--
-- Name: mime_group_mimes mime_group_mimes_mime_group_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mime_group_mimes
    ADD CONSTRAINT mime_group_mimes_mime_group_id_fkey FOREIGN KEY (mime_group_id) REFERENCES public.mime_groups(mime_group_id) ON DELETE CASCADE;


--
-- Name: page_acl page_acl_page_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.page_acl
    ADD CONSTRAINT page_acl_page_id_fkey FOREIGN KEY (page_id) REFERENCES public.pages(page_id) ON DELETE CASCADE;


--
-- Name: page_acl page_acl_usergroup_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.page_acl
    ADD CONSTRAINT page_acl_usergroup_id_fkey FOREIGN KEY (usergroup_id) REFERENCES public.usergroups(usergroup_id) ON DELETE CASCADE;


--
-- Name: pages pages_domain_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pages
    ADD CONSTRAINT pages_domain_id_fkey FOREIGN KEY (domain_id) REFERENCES public.domains(domain_id);


--
-- Name: pages pages_parent_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pages
    ADD CONSTRAINT pages_parent_id_fkey FOREIGN KEY (parent_id) REFERENCES public.pages(page_id) ON DELETE SET NULL;


--
-- Name: plugin_acl plugin_acl_plugin_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plugin_acl
    ADD CONSTRAINT plugin_acl_plugin_id_fkey FOREIGN KEY (plugin_id) REFERENCES public.plugins(plugin_id) ON DELETE CASCADE;


--
-- Name: plugin_acl plugin_acl_usergroup_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plugin_acl
    ADD CONSTRAINT plugin_acl_usergroup_id_fkey FOREIGN KEY (usergroup_id) REFERENCES public.usergroups(usergroup_id) ON DELETE CASCADE;


--
-- Name: plugin_domains plugin_domains_domain_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plugin_domains
    ADD CONSTRAINT plugin_domains_domain_id_fkey FOREIGN KEY (domain_id) REFERENCES public.domains(domain_id);


--
-- Name: plugin_domains plugin_domains_plugin_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plugin_domains
    ADD CONSTRAINT plugin_domains_plugin_id_fkey FOREIGN KEY (plugin_id) REFERENCES public.plugins(plugin_id);


--
-- Name: plugin_endpoints plugin_endpoints_plugin_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plugin_endpoints
    ADD CONSTRAINT plugin_endpoints_plugin_id_fkey FOREIGN KEY (plugin_id) REFERENCES public.plugins(plugin_id) ON DELETE CASCADE;


--
-- Name: plugin_migrations plugin_migrations_plugin_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plugin_migrations
    ADD CONSTRAINT plugin_migrations_plugin_id_fkey FOREIGN KEY (plugin_id) REFERENCES public.plugins(plugin_id) ON DELETE CASCADE;


--
-- Name: pm_setup_history pm_setup_history_domain_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pm_setup_history
    ADD CONSTRAINT pm_setup_history_domain_id_fkey FOREIGN KEY (domain_id) REFERENCES public.domains(domain_id) ON DELETE SET NULL;


--
-- Name: pm_setup_history pm_setup_history_plugin_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pm_setup_history
    ADD CONSTRAINT pm_setup_history_plugin_id_fkey FOREIGN KEY (plugin_id) REFERENCES public.plugins(plugin_id) ON DELETE SET NULL;


--
-- Name: pm_setup_resources pm_setup_resources_recipe_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pm_setup_resources
    ADD CONSTRAINT pm_setup_resources_recipe_id_fkey FOREIGN KEY (recipe_id) REFERENCES public.pgm_recipes(recipe_id) ON DELETE SET NULL;


--
-- Name: pm_setup_resources pm_setup_resources_setup_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pm_setup_resources
    ADD CONSTRAINT pm_setup_resources_setup_id_fkey FOREIGN KEY (setup_id) REFERENCES public.pm_setup_history(setup_id) ON DELETE CASCADE;


--
-- Name: secrets secrets_domain_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.secrets
    ADD CONSTRAINT secrets_domain_id_fkey FOREIGN KEY (domain_id) REFERENCES public.domains(domain_id) ON DELETE CASCADE;


--
-- Name: tokens tokens_2fa_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tokens
    ADD CONSTRAINT tokens_2fa_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id);


--
-- Name: user_auth_identities user_auth_identities_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_auth_identities
    ADD CONSTRAINT user_auth_identities_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON DELETE CASCADE;


--
-- Name: user_profiles user_profiles_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_profiles
    ADD CONSTRAINT user_profiles_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON DELETE CASCADE;


--
-- Name: users users_usergroup_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_usergroup_id_fkey FOREIGN KEY (usergroup_id) REFERENCES public.usergroups(usergroup_id) ON DELETE RESTRICT;


--
-- Name: vc_content_types vc_content_types_ct_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vc_content_types
    ADD CONSTRAINT vc_content_types_ct_id_fkey FOREIGN KEY (ct_id) REFERENCES public.content_types(ct_id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict rxCJb4KCefjBXbY1JSKKy4EJx6Feb5FxE042AoypcQzPKmVgSCTldu7SaEPkltC

-- KAMICORE_INSTALL_POST_DATA_END
