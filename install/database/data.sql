--
-- PostgreSQL database dump
--

\restrict UdlipeCueW0umNZJhL9p7g4U4KGp2nyEVWD9IIcHwg6c0netGWpjIPRylDYf2cM

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
-- Data for Name: usergroups; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.usergroups (usergroup_id, uuid, system_name, is_system, has_api) FROM stdin;
1	2c4b18d5-8bdb-4c37-abdb-6324e285f02f	root	t	t
2	33b3a2a8-08e5-4d3a-ab3e-427b5e1da727	user	t	f
3	4324cc80-e86d-4b64-9c0d-9adea5250cae	guest	t	f
4	c8341510-da95-4910-9ef2-e9104e0ab943	api_users	f	t
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.users (user_id, user_uuid, username, email, password_hash, created_at, last_login, login_data, usergroup_id, is_active, email_verified_at) FROM stdin;
1	711925c5-25c8-472e-bb4e-ba66f8319bc7	admin	\N	\N	\N	\N	\N	1	f	\N
0	ba68a1e5-f5a6-4d78-a350-af22b7205511	Guest	\N	\N	\N	\N	\N	3	t	\N
\.


--
-- Data for Name: api_tokens; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.api_tokens (token_id, user_id, name, token_hash, token_hint, restrictions, is_enabled, created_at, expires_at, last_used_at, revoked_at) FROM stdin;
\.


--
-- Data for Name: plugins; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.plugins (plugin_id, uuid, system_name, plugin_prefix, settings, context_vars, default_language, is_active, created_at, plugin_version, plugin_author, global_settings, default_settings, config) FROM stdin;
2	c19033b7-c7b4-47c3-a3c2-56dc10d2b36f	ThemeManager	tm	[]	\N	en	t	2025-10-12 06:24:56.159124	1.0.1	Kami team	[]	[]	{"handlers":{"manage":{"title":"Manage","actions":{"overview":{"title":"Themes"},"lifecycle":{"title":"Install, update or uninstall theme"}},"default_action":"overview"}},"default_handler":"manage","default_language":"en"}
16	dda755fd-43d9-4e4d-be03-6197b5983b79	Pagination	pgn	[]	\N	en	t	2026-08-23 22:19:52.297088	1.0.0	Kami team	\N	\N	[]
17	2d83b791-abfd-46fa-88db-221c7243a4f1	Mailer	mail	{"host": "", "port": 587, "mailer": "smtp", "charset": "UTF-8", "timeout": 15, "username": "", "from_name": "", "encryption": "tls", "from_email": "", "reply_to_name": "", "reply_to_email": ""}	\N	en	t	2026-08-25 13:09:12.865158	1.0.0	Kami team	\N	\N	{"default_language": "en"}
1	3564f84d-fdac-4250-acb0-b91bcedd5c1c	Navigation	nav	[]	\N	en	t	2025-10-11 04:51:50.539388	1.0.4	Kami team	[]	[]	{"handlers": {"view": {"title": "View", "actions": {"show": {"title": "Show menu"}}, "default_action": "show", "instance_params": {"menu_id": {"type": "item_id", "title": "Menu to display", "default": null, "description": "Select a menu to be displayed.", "content_types": ["navmenu"]}, "template": {"type": "select", "title": "Navigation template", "default": "topnav", "options": [{"title": "Top navigation panel", "value": "topnav"}, {"title": "Footer menu", "value": "footer"}, {"title": "Sidebar menu", "value": "sidebar"}], "description": "Select the template used to render the navigation menu."}}}, "manage": {"title": "Manage", "actions": {"edit": {"title": "Edit menu"}, "list": {"title": "List menus"}, "save": {"title": "Save menu"}, "create": {"title": "Create menu"}, "delete": {"title": "Delete menu"}}, "default_action": "list"}}, "default_handler": "view", "default_language": "en"}
3	18bfd168-a278-42f9-8468-92f0dec931bf	PageManager	pgm	{"test": {"type": "text", "title": "Some setting", "global": 0, "default": "-", "description": ""}}	\N	en	t	2025-12-05 13:32:48.895194	1.0.7	Kami team	[]	{"test": "-"}	{"handlers": {"manage": {"title": "Manage", "actions": {"edit": {"title": "TODO"}, "list": {"title": "TODO"}, "save": {"title": "TODO"}, "config": {"title": "TODO"}, "delete": {"title": "TODO"}, "recipes": {"title": "Page recipes"}, "settings": {"title": "TODO"}, "createPage": {"title": "TODO"}, "deletePage": {"title": "Delete page"}, "recipeSave": {"title": "Save page recipe"}, "domainPages": {"title": "Load domain pages"}, "recipeDelete": {"title": "Delete page recipe"}, "pageLayoutData": {"title": "Load page layout"}, "pluginContextForm": {"title": "TODO"}, "createPageFromRecipe": {"title": "Create page from recipe"}}, "default_action": "list"}}, "default_handler": "manage", "default_language": "en"}
4	a871c1f4-5040-47b8-9f37-19f0e0988020	ContentManager	cm	[]	\N	en	t	2025-12-13 12:42:48.912513	1.2.0	Kami team	[]	[]	{"handlers": {"view": {"title": "View", "actions": {"getItem": {"api": true, "title": "Get content item"}, "getType": {"api": true, "title": "Get content type structure"}, "listTypes": {"api": true, "title": "List content types"}, "searchItems": {"api": true, "title": "Search content items"}}}, "manage": {"title": "Manage", "actions": {"config": {"title": "TODO"}, "itemEdit": {"title": "TODO"}, "itemList": {"title": "TODO"}, "itemSave": {"title": "TODO"}, "typeEdit": {"title": "TODO"}, "typeList": {"title": "TODO"}, "typeSave": {"title": "TODO"}, "fieldEdit": {"title": "Edit content field"}, "fieldList": {"title": "Manage global content fields"}, "fieldMove": {"title": "Reorder content fields"}, "fieldSave": {"title": "Save content field"}, "itemDelete": {"title": "TODO"}, "typeDelete": {"title": "TODO"}, "fieldAttach": {"title": "Attach content field"}, "fieldDelete": {"title": "Delete content field"}, "fieldDetach": {"title": "Detach content field"}, "globalFieldEdit": {"title": "Edit global content field"}, "globalFieldSave": {"title": "Save global content field"}, "typeManagerList": {"title": "Manage content type managers"}, "typeManagerUpdate": {"title": "Update content type manager"}}, "default_action": "typeList"}, "content": {"title": "Content", "actions": {"createItem": {"api": true, "title": "Create content item"}, "deleteItem": {"api": true, "title": "Delete content item"}, "updateItem": {"api": true, "title": "Update content item"}}}, "structure": {"title": "Structure", "actions": {"updateType": {"api": true, "title": "Update content type"}, "attachField": {"api": true, "title": "Attach field"}, "detachField": {"api": true, "title": "Detach field"}, "reorderFields": {"api": true, "title": "Reorder fields"}}}}, "default_handler": "manage", "default_language": "en"}
5	7c294eb7-4faf-4e7b-8d6e-cd85f04285c5	ViewStatic	cv	[]	\N	en	t	2025-12-27 22:11:55.537084	1.0.3	Kami team	[]	[]	{"handlers":{"view":{"title":"View","actions":{"view":{"title":"View"}},"default_action":"view","instance_params":{"item_id":{"title":"Single item","description":"Item to display.","type":"item_id","content_types":["static_content"],"default":0}}},"manage":{"title":"Manage","actions":{"config":{"title":"TODO"}},"default_action":"config"}},"default_handler":"view","default_language":"en"}
6	8c05bbc4-1aad-43f5-8592-923c25976eb2	UserProfile	profile	{"profile_page_enabled": {"type": "checkbox", "title": "Profile page enabled", "global": 0, "default": 1, "description": ""}}	\N	en	t	2026-03-04 20:19:29.061844	1.0.8	Kami team	[]	{"profile_page_enabled": 1}	{"handlers": {"view": {"title": "View", "actions": {"statusbar": {"title": "User status bar"}, "profile_page": {"title": "TODO"}}, "default_action": "statusbar"}, "manage": {"title": "Manage", "actions": {"config": {"title": "TODO"}}, "default_action": "config"}, "account": {"title": "Account", "actions": {"credentials": {"title": "Credentials"}, "change_email": {"title": "Change email"}, "change_password": {"title": "Change password"}, "change_username": {"title": "Change username"}, "remove_password": {"title": "Remove password"}, "disconnect_provider": {"title": "Disconnect provider"}}, "default_action": "credentials"}}, "default_handler": "view", "default_language": "en"}
7	e892c531-2e17-4913-8ff4-9222d0b3db08	UserAccount	account	{"login_action": {"type": "select", "title": "Action after login", "global": 0, "default": "reload", "options": [{"title": "Reload current page", "value": "reload"}, {"title": "Do nothing", "value": "nothing"}, {"title": "Redirect to URL", "value": "redirect"}], "description": "Defines what happens after a successful login."}, "redirect_page": {"type": "url", "title": "Redirect page URL", "global": 0, "default": "/", "description": "The URL to redirect the user to after login when the redirect action is selected."}, "two_factor_auth": {"type": "checkbox", "title": "Two-factor authentication", "global": 1, "default": 1, "description": "Enables two-factor authentication for supported login flows."}, "email_activation": {"type": "checkbox", "title": "Email activation required", "global": 1, "default": 1, "description": "Requires email verification for users who register with email and password."}, "sso_authentication": {"type": "checkbox", "title": "SSO authentication", "global": 0, "default": 1, "description": "Allows users to authenticate through the main domain using single sign-on."}, "registration_enabled": {"type": "checkbox", "title": "Registration enabled", "global": 0, "default": 1, "description": "Allows new users to register on this domain."}, "admin_activation_required": 0}	\N	en	t	2026-03-22 07:01:57.227778	1.1.0	Kami team	{"two_factor_auth": 1, "email_activation": 1}	{"login_action": "reload", "redirect_page": "/", "verification_page": "/", "sso_authentication": 1, "password_reset_page": "/", "registration_enabled": 1}	{"handlers": {"view": {"title": "View", "actions": {"login": {"title": "Sign in"}, "register": {"title": "Register"}, "loginform": {"title": "TODO"}, "reset_password": {"title": "Reset password"}, "resend_verification": {"title": "Resend verification email"}, "request_password_reset": {"title": "Request password reset"}}, "default_action": "loginform"}, "manage": {"title": "Manage", "actions": {"config": {"title": "TODO"}}, "default_action": "config"}}, "default_handler": "view", "default_language": "en"}
8	1241eab1-da3b-47bf-a237-587f7970e062	Forms	form	[]	\N	en	t	2026-08-13 20:17:29.9907	1.3.0	Kami team	[]	[]	{"handlers": {"view": {"title": "View", "actions": {"entityOptions": {"title": "Load system entity options"}, "autocompleteOptions": {"title": "Load autocomplete suggestions"}}, "default_action": "entityOptions"}}, "default_handler": "view", "default_language": "en"}
9	dde69f0e-2a4a-4c6c-b01d-309a93f67c5a	LangSwitcher	ls	[]	\N	en	t	2026-08-16 05:33:57.611572	1.0.0	Kami team	[]	[]	{"handlers": {"view": {"title": "View", "actions": {"view": {"title": "View"}}, "default_action": "view", "instance_params": {"template": {"type": "select", "title": "Language switcher template", "default": "simple", "options": [{"title": "Simple links", "value": "simple"}, {"title": "Flag links", "value": "footer"}, {"title": "Select", "value": "select"}], "description": "Select the template used to render the language switcher."}}}}, "default_handler": "view", "default_language": "en"}
10	be358f3f-97bc-4cb8-aed2-9694da93719c	PluginManager	pm	[]	\N	en	t	2026-08-20 15:13:57.288132	1.1.1	Kami team	[]	[]	{"handlers": {"manage": {"title": "Manage", "actions": {"list": {"title": "Plugins"}, "setup": {"title": "Setup plugin"}, "plugin": {"title": "Plugin settings"}, "lifecycle": {"title": "Plugin lifecycle action"}, "applySetup": {"title": "Apply setup plan"}, "resolveSetup": {"title": "Resolve setup plan"}, "pluginActivation": {"title": "Plugin domain activation"}, "pluginSettingsSave": {"title": "Save plugin settings"}, "pluginDomainSettings": {"title": "Load plugin domain settings"}}, "default_action": "list"}}, "default_handler": "manage", "default_language": "en"}
11	3c6bf6e5-3100-4825-997a-817503bcd287	TextProcessor	text	[]	\N	en	t	2026-08-21 23:04:35.671612	1.0.0	Kami team	\N	\N	[]
12	6f2d882b-4846-42f1-b9f2-c057a1de1a8e	TranslationManager	trm	[]	\N	en	t	2026-08-21 23:22:45.86832	1.0.0	Kami team	\N	\N	{"handlers": {"manage": {"title": "Manage", "actions": {"overview": {"title": "Overview"}, "systemEdit": {"title": "Edit system translation"}, "systemList": {"title": "System entities"}, "systemSave": {"title": "Save system translation"}, "contentEdit": {"title": "Edit content translation"}, "contentList": {"title": "Content"}, "contentSave": {"title": "Save content translation"}, "batchTranslate": {"title": "Batch translate"}, "dictionaryEdit": {"title": "Edit system dictionary"}, "dictionarySave": {"title": "Save system dictionary"}, "systemTranslate": {"title": "Translate system entity"}, "contentTranslate": {"title": "Translate content"}, "dictionaryTranslate": {"title": "Translate system dictionary"}}, "default_action": "overview"}}, "default_handler": "manage", "default_language": "en"}
13	f4c26c79-93b8-4cf2-b56a-647fd1b13b75	SystemManager	sm	[]	\N	en	t	2026-08-22 10:09:46.720575	0.2.0	Kami team	\N	\N	{"handlers":{"manage":{"title":"Manage","actions":{"overview":{"title":"System"},"settings":{"title":"System settings"},"settingsSave":{"title":"Save system settings"},"languages":{"title":"Languages"},"languagesSave":{"title":"Save languages"},"domains":{"title":"Domains"},"domainEdit":{"title":"Edit domain"},"domainSave":{"title":"Save domain"},"secrets":{"title":"Secrets"},"secretSave":{"title":"Save secret"},"secretDelete":{"title":"Delete secret"}},"default_action":"overview"}},"default_handler":"manage","default_language":"en"}
14	3b47fd2b-1227-4bb1-a960-f1ef114b2e87	UserManager	um	[]	\N	en	t	2026-08-22 11:57:25.624485	0.1.2	Kami team	\N	\N	{"handlers": {"manage": {"title": "Manage", "actions": {"acl": {"title": "Permissions"}, "users": {"title": "Users"}, "groups": {"title": "Groups"}, "aclSave": {"title": "Save permissions"}, "overview": {"title": "Users and access"}, "userEdit": {"title": "Edit user"}, "userSave": {"title": "Save user"}, "groupEdit": {"title": "Edit group"}, "groupSave": {"title": "Save group"}, "groupDelete": {"title": "Delete group"}}, "default_action": "overview"}}, "default_handler": "manage", "default_language": "en"}
15	3b2d0e2f-12ae-4d3f-8a45-b9fc0fb71959	Media	media	{"max_upload_size": 50, "allowed_extensions": "jpg,jpeg,png,webp,avif,gif,pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,txt,csv,rtf,mp3,ogg,wav,m4a,mp4,webm,mov,zip"}	\N	en	t	2026-08-23 04:42:21.3587	0.2.0	Kami team	\N	\N	{"handlers": {"view": {"title": "View", "actions": {"browser": {"title": "Media browser"}, "listFiles": {"title": "Browse media files"}}, "default_action": "browser"}, "manage": {"title": "Manage", "actions": {"move": {"title": "Move media item"}, "delete": {"title": "Delete media item"}, "rename": {"title": "Rename media item"}, "upload": {"title": "Upload media files"}, "createFolder": {"title": "Create media folder"}}, "default_action": "upload"}}, "default_handler": "view", "default_language": "en"}
18	7a0c0b7b-e5de-4422-acd6-7d145dcfac73	Notifications	notify	{"expire": 3600}	\N	en	t	2026-08-26 16:30:53.268997	1.1.0	Kami team	\N	\N	{"handlers": {"view": {"title": "View", "actions": {"get": {"title": "Get notifications"}, "view": {"title": "Notification container"}}, "default_action": "view"}}, "default_handler": "view", "default_language": "en"}
19	a66e9c43-ea3b-403d-a7a5-756ca09015ee	ViewArticles	articles	{"default_count": 20, "items_count_values": [10, 20, 50, 0], "items_count_selector": false}	\N	en	t	2026-08-31 14:49:43.423393	1.0.8	Kami team	\N	\N	{"handlers": {"list": {"title": "Article list", "actions": {"list": {"title": "List articles"}}, "default_action": "list", "instance_params": {"items_per_page": {"type": "number", "title": "Items per page", "default": null, "description": "Override the default number of articles displayed per page. Leave empty to use the plugin setting. Use 0 to display all articles."}, "show_pagination": {"type": "checkbox", "title": "Show pagination", "default": true, "description": "Display pagination controls for this article list."}, "articles_category_ids": {"type": "item_id", "title": "Article categories", "default": null, "multiple": true, "description": "Select which article categories to display. Leave empty to display articles from all categories.", "content_types": ["article_category"]}}}, "view": {"title": "Single article", "actions": {"view": {"title": "View article"}}, "default_action": "view", "instance_params": {"items_per_page": {"type": "number", "title": "Items per page", "default": null, "description": "Override the default number of articles displayed per page. Leave empty to use the plugin setting. Use 0 to display all articles."}, "show_pagination": {"type": "checkbox", "title": "Show pagination", "default": true, "description": "Display pagination controls for this article list."}, "articles_category_ids": {"type": "item_id", "title": "Article categories", "default": null, "multiple": true, "description": "Select which article categories to display. Leave empty to display articles from all categories.", "content_types": ["article_category"]}}}}, "default_handler": "list", "default_language": "en"}
20	caf329e2-72bd-4ec7-a6f4-cf78081af6e1	Formatter	fmt	{"date_format": {"en": "M j, Y", "uk": "d.m.Y"}, "currency_format": {"en": "{{symbol}}{{value}}", "uk": "{{value}} {{symbol}}"}, "datetime_format": {"en": "M j, Y, H:i", "uk": "d.m.Y, H:i"}, "decimal_separator": {"en": ".", "uk": ","}, "thousands_separator": {"en": ",", "uk": " "}}	\N	en	t	2026-09-01 09:40:35.383351	1.0.0	Kami team	\N	\N	{"default_language": "en"}
21	016b78c1-25cb-4ca6-8c55-80f0bd62db49	ApiAccess	aa	[]	\N	en	t	2026-09-02 14:03:19.029886	1.0.0	Kami team	\N	\N	{"handlers": {"manage": {"title": "Manage", "actions": {"tokenEdit": {"title": "Edit API token"}, "tokenList": {"title": "List API tokens"}, "tokenSave": {"title": "Save API token"}, "tokenDelete": {"title": "Delete API token"}, "tokenEnable": {"title": "Enable API token"}, "tokenRevoke": {"title": "Revoke API token"}, "tokenDisable": {"title": "Disable API token"}}, "default_action": "tokenList"}}, "default_handler": "manage", "default_language": "en"}
22	10ac77fc-162f-40b5-b82a-bfcda3b9a3c0	Breadcrumbs	bc	{"separator":"›"}	\N	en	t	2026-09-12 00:00:00	1.0.0	Kami team	\N	\N	{"handlers":{"view":{"title":"Breadcrumbs","description":"Render breadcrumb navigation in a page wrapper.","actions":{"renderPlaceholder":{"title":"Render placeholder","description":"Insert a placeholder that is resolved after breadcrumb finalization."}},"default_action":"renderPlaceholder","instance_params":{}}},"default_handler":"view","default_language":"en"}
\.


--
-- Data for Name: content_types; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.content_types (ct_id, uuid, author_id, plugin_id, parent_id, system_name, schema, created_at, has_slug, default_manager_plugin_id, manager_plugin_id, manager_overridden) FROM stdin;
1	75c059a3-d9e8-4572-8d18-5f4823266a99	\N	1	\N	navmenu	{"fields": {"menu_key": {"settings": {"required": true}, "displayorder": 1}, "menu_title": {"settings": {"required": true}, "displayorder": 2}, "menu_description": {"displayorder": 3}, "visible_to_groups": {"settings": {"multiple": true}, "displayorder": 4}}, "title_field": "menu_title", "summary_field": "menu_description"}	2025-12-02 17:50:56.562006	f	1	1	f
2	4ff2a6c5-6b64-4344-a33a-0f14e8b9bc55	\N	1	\N	navmenu_item	{"fields": {"item_url": {"displayorder": 3}, "item_icon": {"displayorder": 4}, "item_type": {"default": "link", "settings": {"required": true}, "displayorder": 1}, "item_title": {"displayorder": 2}, "displayorder": {"settings": {"hidden": true}, "displayorder": 6}, "visible_to_groups": {"settings": {"multiple": true}, "displayorder": 5}}, "title_field": "item_title"}	2025-12-02 17:50:56.56923	f	1	1	f
3	d875f2f6-b69e-4be9-a232-f42f71a3990e	\N	4	\N	static_content	{"fields": {"internal_title": {"settings": {"required": true}, "displayorder": 1}, "static_content_body": {"settings": {"required": true}, "displayorder": 2, "legacy_names": ["content"]}, "static_item_template": {"default": "default", "displayorder": 3}}, "title_field": "internal_title"}	2025-12-13 12:42:48.914354	f	4	4	f
4	5c2f8d58-b584-44e4-b71a-97fecc2512ea	\N	6	\N	user_profile	{"fields": {"displayname": {"displayorder": 1}, "website_url": {"displayorder": 2}}, "title_field": "displayname"}	2026-03-04 20:19:29.062628	f	6	6	f
5	990d42d6-edac-4afe-aa60-9ce338d4163e	1	4	\N	static_content_block	{"fields": {"display_title": {"displayorder": 2}, "block_template": {"default": "default", "displayorder": 4}, "internal_title": {"settings": {"required": true}, "displayorder": 1}, "min_item_width": {"default": "240", "displayorder": 6}, "max_items_per_row": {"default": "4", "displayorder": 5}, "content_items_list": {"settings": {"multiple": true}, "displayorder": 3}}, "title_field": "internal_title"}	2026-08-15 06:09:13.905571	f	4	4	f
6	16dfed6d-74dd-44b8-82c2-0503919bb6db	1	4	\N	article	{"fields": {"tags": {"settings": {"multiple": true}, "displayorder": 9}, "summary": {"displayorder": 6}, "updated_at": {"displayorder": 5}, "article_body": {"settings": {"required": true}, "displayorder": 7, "search_weight": "C"}, "published_at": {"displayorder": 4}, "display_title": {"displayorder": 1}, "related_items": {"settings": {"multiple": true}, "displayorder": 10}, "article_preview": {"displayorder": 8}, "article_categories": {"settings": {"multiple": true}, "displayorder": 2}, "article_primary_category": {"displayorder": 3}}, "title_field": "display_title"}	2026-08-29 19:51:21.197214	t	4	4	f
7	b796252b-e22e-4714-ba0e-69b66aa7926d	1	4	\N	article_category	{"fields": {"summary": {"displayorder": 2}, "category_page": {"displayorder": 3}, "display_title": {"displayorder": 1}}, "title_field": "display_title", "summary_field": "summary"}	2026-08-31 09:43:48.739955	f	4	4	f
\.


--
-- Data for Name: content_acl; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.content_acl (content_acl_id, usergroup_id, ct_id, handler, created_at) FROM stdin;
1	3	6	view	2026-09-03 16:50:19.520598
2	3	7	view	2026-09-03 16:50:19.520598
3	3	1	view	2026-09-03 16:50:19.520598
4	3	2	view	2026-09-03 16:50:19.520598
5	3	4	view	2026-09-03 16:50:19.520598
6	3	3	view	2026-09-03 16:50:19.520598
7	3	5	view	2026-09-03 16:50:19.520598
8	4	6	view	2026-09-03 16:50:57.557202
9	4	7	view	2026-09-03 16:50:57.557202
10	4	1	view	2026-09-03 16:50:57.557202
11	4	2	view	2026-09-03 16:50:57.557202
12	4	4	view	2026-09-03 16:50:57.557202
13	4	3	view	2026-09-03 16:50:57.557202
14	4	5	view	2026-09-03 16:50:57.557202
15	2	6	view	2026-09-03 16:51:26.557351
16	2	7	view	2026-09-03 16:51:26.557351
17	2	1	view	2026-09-03 16:51:26.557351
18	2	2	view	2026-09-03 16:51:26.557351
19	2	4	view	2026-09-03 16:51:26.557351
20	2	3	view	2026-09-03 16:51:26.557351
21	2	5	view	2026-09-03 16:51:26.557351
\.


--
-- Data for Name: content_items; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.content_items (item_id, item_uuid, ct_id, author_id, plugin_id, item_slug, parent_id, common_data, created_at, updated_at, domain_scope, domains, item_settings) FROM stdin;
1	d6365b6e-b6f6-4059-9dc7-8f0ec1269573	1	1	1	\N	\N	{"menu_key": "front-nav"}	2025-12-02 17:56:38.539087	2026-09-06 02:22:54.535505	0	\N	\N
4	051a0a79-f02a-4dc9-b3ce-9631139fe486	1	1	1	\N	\N	{"menu_key": "user-context"}	2025-12-04 03:52:07.563812	2026-09-05 05:11:26.238699	0	\N	\N
5	f702280f-939f-48f9-94c2-b94474e67af6	1	1	1	\N	\N	{"menu_key": "admin-sidebar"}	2025-12-04 03:58:37.114197	2026-09-03 05:25:28.605289	0	\N	\N
6	e35594b9-184e-4c0f-bc2d-9eee9959b343	3	1	4	\N	\N	{"internal_title": "intro", "static_item_template": "card"}	2025-12-15 07:28:12.422609	2026-08-29 13:46:59.396414	0	\N	\N
7	2f561283-698c-4989-b5ef-4a7cfdcea34d	3	1	4	\N	\N	{"internal_title": "what_is - 1", "static_item_template": "card"}	2025-12-18 06:58:11.22521	2026-09-04 10:41:58.763239	0	\N	\N
8	66859df9-5e84-431e-9929-b7a19b9f2570	3	1	4	\N	\N	{"internal_title": "what_is - 2", "static_item_template": "card"}	2025-12-18 06:59:08.72488	2026-09-04 10:42:56.825956	0	\N	\N
9	96de770c-a18f-4718-ac80-484075827ead	3	1	4	\N	\N	{"internal_title": "what_is - 3", "static_item_template": "card"}	2025-12-19 17:45:17.540506	2026-09-04 10:47:28.802345	0	\N	\N
10	a659115d-b172-458e-974c-3e40d112d564	3	1	4	\N	\N	{"internal_title": "what_is - 4", "static_item_template": "card"}	2025-12-22 12:54:47.729491	2026-09-04 10:48:27.318315	0	\N	\N
11	bbf7c42e-9e6b-4ee5-b20c-4d15a65565cb	2	1	1	\N	0	{"item_url": "", "item_icon": "", "displayorder": "0"}	2026-01-30 20:09:35.688355	2026-01-30 20:09:35.688355	0	\N	\N
12	a3c1739e-94a9-4397-b54b-3084242ee292	2	1	1	\N	0	{"item_url": "about", "item_icon": "", "displayorder": "1"}	2026-01-30 20:09:35.691368	2026-01-30 20:09:35.691368	0	\N	\N
13	cffa0508-28a8-4377-93de-d094bceccce0	5	1	4	\N	\N	{"block_template": "responsive_grid", "internal_title": "Content grid (2x2)", "min_item_width": "360", "max_items_per_row": "2", "content_items_list": ["36", "37", "38", "41"]}	2026-08-15 07:39:24.077843	2026-09-03 17:09:22.662858	0	\N	\N
14	2d545e1a-af53-4df0-8377-97234528c8b2	3	1	4	\N	\N	{"internal_title": "Static top box", "static_item_template": "default"}	2026-08-15 13:18:14.836535	2026-09-04 10:40:25.053864	0	\N	\N
15	adddbefc-7afa-4f9b-b337-81a3434760c4	3	1	4	\N	\N	{"internal_title": "Home hero", "static_item_template": "default"}	2026-08-15 15:12:49.070679	2026-08-29 08:14:00.95826	0	\N	\N
16	53813421-b7aa-4454-b104-13988872b244	3	1	4	\N	\N	{"internal_title": "Static bottom box", "static_item_template": "compact"}	2026-08-15 15:22:23.648813	2026-09-04 10:48:54.454954	0	\N	\N
27	6b53edee-0889-409a-b195-e3dd0ca56f91	6	1	4	just-article	\N	{"updated_at": "", "published_at": "2026-08-30T22:44", "article_preview": "", "article_categories": ["31038"], "article_primary_category": "31038"}	2026-08-30 19:46:04.608736	2026-09-06 02:02:59.792935	0	\N	\N
28	019dc822-41f5-4d3c-b01b-7c2e72a32df7	6	1	4	light-shape-space	\N	{"updated_at": "", "published_at": "2026-08-30T22:45", "related_items": ["31037"], "article_preview": "/media/articles/2026/08/article1.webp", "article_categories": ["31039"], "article_primary_category": ""}	2026-08-30 19:52:39.46743	2026-09-06 02:07:01.845446	0	\N	\N
29	e78310dd-6c41-436b-b0a7-ec91fdd9a1f7	6	1	4	try-nevelyki-zminy	\N	{"updated_at": "", "published_at": "2026-08-30T23:30", "related_items": ["31036"], "article_preview": "/media/articles/2026/08/article2.webp", "article_categories": ["31039"], "article_primary_category": ""}	2026-08-30 20:51:20.882828	2026-09-06 01:57:49.06509	0	\N	\N
30	c86cc1b0-2a8d-41f1-a666-f70f2fd6e0d5	7	1	4	\N	\N	{"category_page": "26"}	2026-08-31 11:20:17.000635	2026-09-01 17:57:22.439866	0	\N	\N
31	3045aa8a-b1a9-497f-8c6b-f90207d4e4e8	7	1	4	\N	\N	{"category_page": "39"}	2026-08-31 11:21:53.143553	2026-09-01 17:57:13.045008	0	\N	\N
34	b5557202-85ac-4efa-8356-247b518d93c4	1	1	1	\N	\N	{"menu_key": "user-sidebar", "visible_to_groups": [1, 5]}	2026-09-02 15:04:32.759077	2026-09-05 05:12:04.688561	0	\N	\N
35	02ea678c-49ca-4b8e-aba2-5f420a6879eb	2	1	1	\N	34	{"item_url": "account", "item_icon": "key-round", "item_type": "link", "displayorder": 0}	2026-09-02 15:06:36.790417	2026-09-05 05:12:04.690817	0	\N	\N
43	b4f07fac-f5f6-4306-a837-855742abc702	3	1	4	\N	\N	{"internal_title": "footer text", "static_item_template": "default"}	2026-09-04 18:25:14.315449	2026-09-04 18:26:47.653965	0	\N	\N
42	3bf97882-9eb1-4591-86e0-bc5a5edf430a	2	1	1	\N	34	{"item_url": "account-api", "item_icon": "settings", "item_type": "link", "displayorder": 1, "visible_to_groups": [5, 1]}	2026-09-03 15:16:36.932779	2026-09-05 05:12:04.691632	0	\N	\N
36	a5f7b99c-df1b-4e9b-899e-d74fbb06ecab	2	1	1	\N	34	{"item_url": "auth/logout", "item_icon": "log-out", "item_type": "link", "displayorder": 2}	2026-09-02 15:06:36.793187	2026-09-05 05:12:04.692401	0	\N	\N
26	121407bf-536c-4e06-9b4a-ce45eaf9e497	2	1	1	\N	5	{"item_url": "admin-media", "item_icon": "image-up", "item_type": "link", "displayorder": 5}	2026-08-24 14:24:29.670341	2026-09-03 05:25:28.610831	0	\N	\N
25	e3d061bb-176e-4c5c-a3e2-d95a333aa307	2	1	1	\N	5	{"item_url": "admin-content", "item_icon": "book-open-text", "item_type": "link", "displayorder": 3}	2026-08-24 14:24:29.668861	2026-09-03 05:25:28.609534	0	\N	\N
24	80b7d96d-2d2d-4533-bd75-6295e744a792	2	1	1	\N	5	{"item_url": "admin-plugins", "item_icon": "plug", "item_type": "link", "displayorder": 2}	2026-08-24 14:24:29.667619	2026-09-03 05:25:28.608882	0	\N	\N
23	a74fe70f-b820-443e-8daf-c413d34eb110	2	1	1	\N	5	{"item_url": "admin-nav", "item_icon": "menu", "item_type": "link", "displayorder": 1}	2026-08-23 16:03:23.951438	2026-09-03 05:25:28.608209	0	\N	\N
22	7d394773-29a3-42b5-8970-8fbee6284d80	2	\N	1	\N	5	{"item_url": "admin-users", "item_icon": "users-round", "item_type": "link", "displayorder": 6}	2026-08-22 11:58:08.073394	2026-09-03 05:25:28.611487	0	\N	\N
21	4e19dfae-e1de-4bbe-bf49-cbacd3f52f12	2	\N	1	\N	5	{"item_url": "admin-system", "item_icon": "sliders-horizontal", "item_type": "link", "displayorder": 7}	2026-08-22 10:13:22.169321	2026-09-03 05:25:28.612132	0	\N	\N
19	a0455cb5-a499-493e-bb27-d253435beaca	2	1	1	\N	5	{"item_url": "admin-pages", "item_icon": "file-pen", "item_type": "link", "displayorder": 0}	2026-08-20 14:51:02.803605	2026-09-03 05:25:28.607371	0	\N	\N
20	f1d7852c-bdea-4800-ad5e-d136b0b5a712	2	1	1	\N	5	{"item_url": "admin-translations", "item_icon": "globe", "item_type": "link", "displayorder": 4}	2026-08-21 23:36:58.441086	2026-09-03 05:25:28.610181	0	\N	\N
44	35f8e964-cb56-434c-9ebc-fbc827a08eb2	2	1	1	\N	5	{"item_url": "/admin-test", "item_icon": "", "item_type": "link", "displayorder": 10}	2026-09-05 14:01:46.995382	2026-09-05 14:01:46.995382	0	\N	\N
38	bde0603f-e945-4b2c-95f5-3bd6d429ef86	2	1	1	\N	5	{"item_url": "auth/logout", "item_icon": "log-out", "item_type": "link", "displayorder": 9}	2026-09-03 05:25:28.613772	2026-09-03 05:25:28.613986	0	\N	\N
37	b9d068fc-0b06-432a-9eca-11de9fc93125	2	1	1	\N	5	{"item_url": "admin-themes", "item_icon": "layout-dashboard", "item_type": "link", "displayorder": 8}	2026-09-03 05:25:28.612816	2026-09-03 05:25:28.61306	0	\N	\N
18	13c52e01-3894-4a06-8b5f-df2d4f55f966	2	1	1	\N	4	{"item_url": "admin", "item_icon": "wrench", "item_type": "link", "displayorder": 2, "visible_to_groups": [1]}	2026-08-20 14:49:59.925498	2026-09-05 05:11:26.242482	0	\N	\N
17	b7035ae7-3fc5-4da0-a099-f7a809ea5941	2	1	1	\N	4	{"item_url": "account", "item_icon": "key-round", "item_type": "link", "displayorder": 0}	2026-08-20 14:49:59.923361	2026-09-05 05:11:26.240856	0	\N	\N
32	39db37bf-1cdf-4fd6-99f1-6acbccbb7dbe	2	1	1	\N	4	{"item_url": "auth/logout", "item_icon": "log-out", "item_type": "link", "displayorder": 3}	2026-08-31 13:24:21.613388	2026-09-05 05:11:26.243228	0	\N	\N
33	0a72ab93-78f3-4299-807e-324cd346526e	2	1	1	\N	4	{"item_url": "account-api", "item_icon": "settings", "item_type": "link", "displayorder": 1, "visible_to_groups": [1, 5]}	2026-09-02 14:30:40.450217	2026-09-05 05:11:26.241675	0	\N	\N
40	422800ad-bfec-4b87-a473-e10238ced719	2	1	1	\N	3	{"item_url": "about-kami", "item_icon": "", "item_type": "link", "displayorder": 0}	2026-09-03 12:14:45.340813	2026-09-06 02:22:54.539811	0	\N	\N
41	558821bc-781a-464e-a680-6e2a9e323edd	2	1	1	\N	3	{"item_url": "about-everything", "item_icon": "", "item_type": "link", "displayorder": 1}	2026-09-03 12:14:45.341764	2026-09-06 02:22:54.540445	0	\N	\N
2	494d53ed-18e7-44e3-b3b1-79eecfd5ee34	2	1	1	\N	1	{"item_url": "static-page", "item_icon": "", "item_title": null, "displayorder": 1}	2025-12-02 18:04:24.297798	2026-09-06 02:22:54.538368	0	\N	\N
39	053b11e5-3a8a-47ae-a6e2-97bd0d321a3d	2	1	1	\N	1	{"item_url": "/", "item_icon": "", "item_type": "link", "displayorder": 0}	2026-09-03 12:10:49.104387	2026-09-06 02:22:54.537554	0	\N	\N
3	7a8a658a-d9f1-412f-9aeb-a8f203062f27	2	1	1	\N	1	{"item_url": "blog", "item_icon": "", "item_title": null, "displayorder": 2}	2025-12-02 18:04:24.299116	2026-09-06 02:22:54.539073	0	\N	\N
\.


--
-- Data for Name: domains; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.domains (domain_id, domain_name, title, description, domain_config, is_root, theme_id, domain_uuid) FROM stdin;
1	example.invalid	First Test Domain	Some description text...	{"languages": ["en", "uk"], "default_language": "en"}	t	1	ba759768-8748-4108-b406-b710e937f985
\.


--
-- Data for Name: domain_aliases; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.domain_aliases (domain_id, alias_name) FROM stdin;
\.


--
-- Data for Name: field_types; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.field_types (type_id, uuid, system_name, type_settings, parent_id) FROM stdin;
1	b485a84e-abb0-4f35-afbb-bff0877f2611	text	{"input":[],"output":[],"storage":{"normalizer":"string"},"sanitize":{"handler":"plain_text"},"parameters":{"max_length":{"type":"integer","title":"field_parameter_max_length","attributes":{"min":0},"description":"field_parameter_max_length_help"},"min_length":{"type":"integer","title":"field_parameter_min_length","attributes":{"min":0},"description":"field_parameter_min_length_help"},"placeholder":{"type":"string","title":"field_parameter_placeholder","description":"field_parameter_placeholder_help"}},"validation":{"rules":["string"]},"compound_component":true}	0
2	10bc994f-613b-403f-8325-5c45d936d85b	number	{"input":{"template":"number_input"},"output":{"template":"number_output"},"storage":{"normalizer":"number"},"sanitize":{"handler":"plain_text"},"parameters":{"max":{"type":"decimal","title":"field_parameter_max","description":"field_parameter_max_help"},"min":{"type":"decimal","title":"field_parameter_min","description":"field_parameter_min_help"},"step":{"type":"decimal","title":"field_parameter_step","attributes":{"min":0},"description":"field_parameter_step_help"}},"validation":{"rules":["numeric"]},"compound_component":true}	0
3	773bd1a9-af28-401f-98ae-bcf67fbfe00d	date	{"input":{"template":"date_input"},"output":{"renderer":"date_output","template":"date_output"},"storage":{"normalizer":"date"},"sanitize":{"handler":"plain_text"},"parameters":{"max":{"type":"string","title":"field_parameter_latest","description":"field_parameter_latest_help"},"min":{"type":"string","title":"field_parameter_earliest","description":"field_parameter_earliest_help"}},"validation":{"rules":["date"]},"compound_component":true}	0
4	97c33e18-0fe1-4f1b-af2d-8c23180efe3e	boolean	{"input":{"renderer":"checkbox_input","template":"checkbox_input"},"output":{"renderer":"boolean_output","template":"boolean_output"},"storage":{"normalizer":"boolean"},"sanitize":{"handler":"plain_text"},"validation":{"rules":["boolean"]},"compound_component":true}	0
27	f9bbbf08-b328-4019-9455-991eedc80528	time	{"input":{"template":"time_input"},"output":{"renderer":"time_output","template":"time_output"},"storage":{"normalizer":"time"},"sanitize":{"handler":"plain_text"},"parameters":{"max":{"type":"string","title":"field_parameter_latest","description":"field_parameter_latest_help"},"min":{"type":"string","title":"field_parameter_earliest","description":"field_parameter_earliest_help"}},"validation":{"rules":["time"]},"compound_component":true}	0
7	00992fd8-c284-4c17-8676-00ad240c71f1	richtext	{"input":{"renderer":"richtext_input","template":"textarea"},"output":{"renderer":"richtext_output","template":"richtext_output"},"storage":{"normalizer":"string"},"sanitize":{"handler":"html_basic"},"validation":{"rules":["string"]}}	1
6	217f9ade-fad3-408d-b6dc-689a792cfc3e	textarea	{"input":{"template":"textarea"},"output":{"template":"plain_text"},"storage":{"normalizer":"string"},"sanitize":{"handler":"plain_text"},"parameters":{"rows":{"type":"integer","title":"field_parameter_rows","default":6,"attributes":{"max":100,"min":2},"description":"field_parameter_rows_help"}},"validation":{"rules":["string"]}}	1
5	b67f96c4-e8a3-4639-ad97-f03edb669a10	string	{"input":{"template":"text_input"},"output":{"template":"plain_text"},"storage":{"normalizer":"string"},"sanitize":{"handler":"plain_text"},"validation":{"rules":["string"]}}	1
12	3d845d0c-da0b-4ee6-bd2a-34e1b7d355ce	decimal	{"input":{"template":"number_input"},"output":{"template":"number_output"},"storage":{"normalizer":"decimal"},"sanitize":{"handler":"plain_text"},"validation":{"rules":["numeric"]}}	2
11	b3596657-c099-47a0-8277-0d7d9d305034	integer	{"input":{"template":"number_input"},"output":{"template":"number_output"},"storage":{"normalizer":"integer"},"sanitize":{"handler":"plain_text"},"parameters":{"step":{"type":"integer","default":1,"attributes":{"min":1}}},"validation":{"rules":["integer"]}}	2
16	f5ba0433-36f4-4a83-a6f3-f604bff81452	datetime	{"input":{"template":"datetime_input"},"output":{"renderer":"datetime_output","template":"datetime_output"},"storage":{"normalizer":"date"},"sanitize":{"handler":"plain_text"},"validation":{"rules":["datetime"]}}	3
15	8fdf4116-4f22-4dc0-85d8-bc264a6f1834	date_full	{"input":{"template":"date_input"},"output":{"renderer":"date_output","template":"date_output"},"storage":{"normalizer":"date"},"sanitize":{"handler":"plain_text"},"validation":{"rules":["date"]}}	3
14	8d2bb672-f0a0-429e-baa5-ccbd3c49d67d	year_month	{"input":{"template":"year_month_input"},"output":{"template":"plain_text"},"storage":{"normalizer":"date"},"sanitize":{"handler":"plain_text"},"validation":{"rules":["year_month"]}}	3
13	7d3b76f9-0b30-48ad-a461-e6ff54b52f01	year	{"input":{"template":"year_input"},"output":{"template":"plain_text"},"storage":{"normalizer":"date"},"sanitize":{"handler":"plain_text"},"validation":{"rules":["year"]}}	3
25	294fba83-f58b-48e1-a213-381354cf3415	yesno	{"input":{"renderer":"yesno_input","template":"yesno_input"},"output":{"renderer":"boolean_output","template":"yesno_output"},"storage":{"normalizer":"boolean"},"sanitize":{"handler":"plain_text"},"validation":{"rules":["boolean"]}}	4
24	7e1a5820-7247-4a83-b5b7-fa45518cda52	checkbox	{"input":{"renderer":"checkbox_input","template":"checkbox_input"},"output":{"renderer":"boolean_output","template":"boolean_output"},"storage":{"normalizer":"boolean"},"sanitize":{"handler":"plain_text"},"validation":{"rules":["boolean"]}}	4
30	3798b10a-78ed-44c2-92d8-e8d26fda3ac8	autocomplete	{"parameters":{"source_fields":{"type":"field_id","title":"field_parameter_source_fields","value":"system_name","default":[],"multiple":true,"required":false,"description":"field_parameter_source_fields_help"}},"requires_indexed":true}	5
17	ce4d481a-cf9d-4f7c-95e4-68dea6d26be9	select	{"input":{"renderer":"select_input","template":"select_input"},"output":{"template":"plain_text"},"storage":{"normalizer":"string"},"sanitize":{"handler":"plain_text"},"functions":{"edit":"buildSelect"},"templates":{"edit":"form-select"},"parameters":{"options":{"type":"textarea","title":"field_parameter_options","format":"json","default":[],"description":"field_parameter_options_help"}},"validation":{"rules":["string"]}}	5
10	fde86f13-d94b-4549-9551-625bd249e903	slug	{"input":{"template":"text_input"},"output":{"template":"plain_text"},"storage":{"normalizer":"string"},"sanitize":{"handler":"slug"},"validation":{"rules":["string","slug"]}}	5
9	90a3d1d4-e570-4df2-a91e-b8ba48e6930f	url	{"input":{"template":"url_input"},"output":{"template":"link_output"},"storage":{"normalizer":"string"},"sanitize":{"handler":"plain_text"},"validation":{"rules":["string","url"]}}	5
8	14580f8b-da3c-47e6-94b4-a05a75aa0d1f	email	{"input":{"template":"email_input"},"output":{"template":"plain_text"},"storage":{"normalizer":"string"},"sanitize":{"handler":"plain_text"},"validation":{"rules":["string","email"]}}	5
29	10a83691-48f7-48c8-b6bd-d5fe3dfe2282	media	{"parameters":{"root":{"type":"string","title":"field_parameter_media_root","description":"field_parameter_media_root_help"},"accept":{"type":"string","title":"field_parameter_media_accept","description":"field_parameter_media_accept_help"}}}	9
28	a7349b6f-7771-4be9-8bca-f321c812d090	usergroup_id	{"input":{"template":"number_input"},"output":{"template":"number_output"},"storage":{"normalizer":"integer"},"sanitize":{"handler":"plain_text"},"parameters":{"max":null,"min":null,"step":null},"validation":{"rules":["integer"]},"compound_component":false}	11
26	55c0f873-25ee-4094-9ea9-e95eb6b402b4	user_id	{"input":{"template":"number_input"},"output":{"template":"number_output"},"storage":{"normalizer":"integer"},"sanitize":{"handler":"plain_text"},"parameters":{"max":null,"min":null,"step":null},"validation":{"rules":["integer"]},"compound_component":false}	11
23	a1fd8d38-d28c-4bff-9511-d93940a0c49f	item_id	{"input":{"template":"number_input"},"output":{"template":"number_output"},"storage":{"normalizer":"integer"},"sanitize":{"handler":"plain_text"},"functions":{"edit":"editContentItem"},"parameters":{"max":null,"min":null,"step":null,"content_types":{"type":"content_type_id","title":"field_parameter_content_types","value":"system_name","default":[],"multiple":true,"required":true,"description":"field_parameter_content_types_help"}},"validation":{"rules":["integer"]},"compound_component":false}	11
22	66e8205b-e0fd-4ce4-ad74-352da9c5f56a	field_id	{"input":{"template":"number_input"},"output":{"template":"number_output"},"storage":{"normalizer":"integer"},"sanitize":{"handler":"plain_text"},"parameters":{"max":null,"min":null,"step":null},"validation":{"rules":["integer"]},"compound_component":false}	11
21	4f4fb317-c7c3-47db-8c14-7805f2b33213	field_type_id	{"input":{"template":"number_input"},"output":{"template":"number_output"},"storage":{"normalizer":"integer"},"sanitize":{"handler":"plain_text"},"parameters":{"max":null,"min":null,"step":null},"validation":{"rules":["integer"]},"compound_component":false}	11
20	ddb21e75-0c06-4f5d-a2ae-a2c35dacfeae	content_type_id	{"input":{"template":"number_input"},"output":{"template":"number_output"},"storage":{"normalizer":"integer"},"sanitize":{"handler":"plain_text"},"parameters":{"max":null,"min":null,"step":null},"validation":{"rules":["integer"]},"compound_component":false}	11
19	9a56eb7d-aadf-4044-85a7-85242caddf4e	page_id	{"input":{"template":"number_input"},"output":{"template":"number_output"},"storage":{"normalizer":"integer"},"sanitize":{"handler":"plain_text"},"parameters":{"max":null,"min":null,"step":null},"validation":{"rules":["integer"]},"compound_component":false}	11
18	ba1e3f78-1fd7-4044-9b36-00a8173fe6c1	domain_id	{"input":{"template":"number_input"},"output":{"template":"number_output"},"storage":{"normalizer":"integer"},"sanitize":{"handler":"plain_text"},"parameters":{"max":null,"min":null,"step":null},"validation":{"rules":["integer"]},"compound_component":false}	11
31	9ba25fe2-b9b9-4060-a200-fc286302c949	compound	{"input":{"template":"compound"},"storage":{"normalizer":"compound"},"parameters":{"components":{"type":"compound_components","title":"field_parameter_components","description":"field_parameter_components_help","default":[],"required":true}},"validation":{"rules":["compound"]}}	0
\.


--
-- Data for Name: field_variants; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.field_variants (variant_id, variant_uuid, type_id, variant_name, variant_title, variant_description, variant_settings) FROM stdin;
1	6cfd22d3-8c09-43fa-84f9-5db73a5df602	1	string	string	\N	\N
2	3bba0cff-de34-4db0-aebf-1e6945ec73ea	1	plain_text	plain text	\N	\N
3	bcad116f-8765-402b-97d9-453e99e82dec	1	html	html	\N	\N
4	80ac7cc8-d436-415a-9786-4b301ef7699a	1	url	url	\N	\N
5	ea3d7e4b-1009-4d58-a8ce-83f0d5de206c	2	integer	integer	\N	\N
6	c9c9a97b-ea1f-4e24-a315-3057a4f4df76	2	float	float	\N	\N
7	7c9918a4-0773-4adb-a2c5-03c207440f2a	2	currency	currency	\N	\N
8	bffe2257-a829-4727-a87b-ffee774930ec	2	user_id	user	\N	\N
9	6d415be6-5f83-4cc6-8e2c-601c8c647101	2	usergroup_id	usergroup	\N	\N
10	bfef030f-6ac5-4423-b1cb-1aa2fa06f40f	2	page_id	page	\N	\N
11	fce0e79a-7416-47cb-b869-c8742e373b43	2	domain_id	domain	\N	\N
12	6920f864-3b6d-417a-b52e-c8d7d20abce5	2	ct_id	content type	\N	\N
13	bc50c8dd-e512-4fc1-99eb-19fa999b0e79	2	field_id	field	\N	\N
14	d072fe9d-7ca0-45e7-8fa0-77209721783f	2	checkbox	checkbox	\N	{"functions": {"edit": "editCheckbox", "save": "saveCheckbox", "view": "viewCheckbox"}, "templates": {"edit": "form-checkbox", "view": "checked"}}
15	972f0b59-94d6-4abb-953d-3f8de98f2588	2	item_id	content item	\N	{"functions": {"edit": "editContentItem"}}
16	3e4d63cc-9766-406f-aaee-080cedcecfe2	1	media	media	\N	\N
17	42e32f40-1502-4ceb-bec5-f50f0845ab34	1	select	select	\N	{"functions": {"edit": "buildSelect", "view": "viewSelect"}, "templates": {"edit": "form-select", "view": "view-select"}}
18	ebb4389b-583c-4009-ac60-07ee3d58c3b6	3	date	date	\N	\N
19	4e4b1c5b-9f5d-4223-a64c-6e520f908921	3	datetime	datetime	\N	\N
\.


--
-- Data for Name: fields; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.fields (field_id, uuid, type_id, variant_id, system_name, field_settings) FROM stdin;
1	ec836f0c-9db5-418a-92e3-395ad5c15dcd	5	\N	displayname	{"unique": true, "indexed": true, "translatable": false}
2	1dec29c1-3552-4ee6-ab7d-21f0677205ec	9	\N	website_url	{"unique": false, "indexed": false, "translatable": false}
3	5f70bf1a-9a34-4334-a40d-d9f82e0d7abd	5	\N	menu_title	{"unique": false, "indexed": false, "translatable": true}
4	6eb79fcf-f1b4-468c-89f3-3b6b437d9ed0	6	\N	menu_description	{"unique": false, "indexed": false, "translatable": true}
5	771b41b7-9373-4ea2-862d-5e452d8c67da	5	\N	item_title	{"unique": false, "indexed": false, "translatable": true}
6	618aa226-545e-490a-aa5a-4d5b4dc0f389	5	\N	item_url	{"unique": false, "indexed": false, "translatable": false}
7	f4ec7f26-95e0-4c80-b957-535a8f955f7a	5	\N	item_icon	{"unique": false, "indexed": false, "translatable": false}
8	eca96222-7643-4545-bd1a-b02e959fdb6c	11	\N	displayorder	{"unique": false, "indexed": false, "translatable": false}
9	44bf3a4d-3b78-46d1-a7d5-44b711705c1a	7	\N	static_content_body	{"unique": false, "indexed": true, "translatable": true}
10	08a10f8a-cf9f-4875-9fe1-f49b8172505d	16	\N	published_at	{"unique": false, "indexed": true, "translatable": false}
11	298011f1-17db-467c-8c57-e8ca67f63915	16	\N	updated_at	{"unique": false, "indexed": true, "translatable": false}
12	c30cc95c-776a-44dc-8d65-bf0e110f6dcb	6	\N	summary	{"params": {"rows": 6}, "unique": false, "indexed": true, "translatable": true}
13	90c6a493-1ad9-4bac-a1fa-27800fafb10c	7	\N	article_body	{"unique": false, "indexed": true, "translatable": true}
14	8217bf2c-21de-437d-8efd-2de368e79d4f	17	\N	item_type	{"params": {"options": [{"title": "Link", "value": "link"}, {"title": "Heading", "value": "heading"}, {"title": "Divider", "value": "divider"}]}, "unique": false, "indexed": false, "translatable": false}
15	0d8b9ff4-a9f5-4d9f-988c-077334c93d15	5	\N	internal_title	{"unique": false, "indexed": true, "translatable": false}
16	9bda5536-e965-4359-a6ad-fdee8814a0aa	23	\N	related_items	{"params": {"content_types": ["article", "card"]}, "unique": false, "indexed": true, "translatable": false}
17	33b60373-705d-4b9c-892a-40fda0aa64dc	17	\N	static_item_template	{"params": {"options": [{"title": "Default", "value": "default"}, {"title": "Container", "value": "container"}, {"title": "Card", "value": "card"}, {"title": "Compact", "value": "compact"}]}, "unique": false, "indexed": false, "translatable": false}
18	98a8eb58-ea4e-4090-aed6-dadcc6be76d9	23	\N	content_items_list	{"params": {"content_types": ["static_content"]}, "unique": false, "indexed": false, "translatable": false}
19	ea6d332b-27c1-46e5-a062-aa29fd9a759f	5	\N	menu_key	{"unique": false, "indexed": true, "translatable": false}
20	5995a472-db4f-4737-bded-ecb86e441981	17	\N	block_template	{"params": {"options": [{"title": "Default", "value": "default"}, {"title": "Card", "value": "card"}, {"title": "Compact", "value": "compact"}, {"title": "Responsive grid", "value": "responsive_grid"}]}, "unique": false, "indexed": false, "translatable": false}
21	8f37a3d0-e84a-4e53-a2c3-4fbbf47c977c	28	\N	visible_to_groups	{"unique": false, "indexed": false, "translatable": false}
22	cb599413-35fe-4972-8cc7-21ec10d7f7f7	29	\N	article_preview	{"unique": false, "indexed": false, "translatable": false}
23	159467a7-5444-4cb2-a050-a790a0111ab1	30	\N	tags	{"params": {"source_fields": ["tags"]}, "unique": false, "indexed": true, "translatable": true}
24	aca53e19-27a2-40f8-97d2-105428ed9c3a	5	\N	display_title	{"unique": false, "indexed": true, "translatable": true}
25	12dbf4d1-e811-4eec-b4da-ea8fb488d40c	11	\N	max_items_per_row	{"params": {"max": 12, "min": 1, "step": 1}, "unique": false, "indexed": false, "translatable": false}
26	4aa20cf5-36f7-4e98-a57d-13846a1a06c5	11	\N	min_item_width	{"params": {"max": 5000, "min": 10, "step": 10}, "unique": false, "indexed": false, "translatable": false}
27	74e0be37-2b6d-4b01-b5f8-048a6e309b71	23	\N	article_categories	{"params": {"content_types": ["article_category"]}, "unique": false, "indexed": true, "translatable": false}
28	b2ea1f58-00f2-4b3c-be49-5e9b64ef2de2	11	\N	test_multiple_int	{"params": {"step": 1}, "unique": false, "indexed": true, "translatable": false}
29	7f03bfc8-d082-4c3e-8653-08cf9bb9125d	19	\N	category_page	{"unique": false, "indexed": false, "translatable": false}
30	b14eaea7-67a1-4160-9afe-a5547e8712bf	23	\N	article_primary_category	{"params": {"content_types": ["article_category"]}, "unique": false, "indexed": false, "translatable": false}
\.


--
-- Data for Name: field_options; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.field_options (option_id, option_uuid, field_id, option_title, option_value, parent_id) FROM stdin;
\.


--
-- Data for Name: global_settings; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.global_settings (varname, value, settings) FROM stdin;
session_timeout	14400	\N
cache_enabled	1	\N
cache_ttl	300	\N
lifecycle_plugins	[]	\N
system_css	["/assets/css/system.css","/assets/vendor/tom-select/tom-select.css"]	\N
system_js	["/assets/js/icons.js","/assets/js/common.js","/assets/vendor/tom-select/tom-select.complete.js","/assets/js/tom-select-init.js"]	\N
system_custom_code		\N
usergroup_root	1	\N
usergroup_default	2	\N
usergroup_guest	3	\N
default_timezone	UTC	\N
\.


--
-- Data for Name: item_bools; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.item_bools (id, item_id, field_id, value) FROM stdin;
\.


--
-- Data for Name: item_dates; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.item_dates (id, item_id, field_id, value) FROM stdin;
1	29	10	2026-08-30 23:30:00+00
2	27	10	2026-08-30 22:44:00+00
3	28	10	2026-08-30 22:45:00+00
\.


--
-- Data for Name: item_nums; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.item_nums (id, item_id, field_id, value) FROM stdin;
1	29	16	28
2	29	27	31
3	27	27	30
4	28	16	29
5	28	27	31
\.


--
-- Data for Name: item_relations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.item_relations (item_id, related_id) FROM stdin;
\.


--
-- Data for Name: item_texts; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.item_texts (id, item_id, field_id, lang_code, value, tsv) FROM stdin;
6	6	9	en	From simple to complex—one model. This is a static HTML block. You can create, edit, and translate these blocks separately from the site's structure, and then add them to pages in the desired locations.	'add':29 'block':12,20 'complex':4 'creat':15 'desir':35 'edit':16 'html':11 'locat':36 'model':6 'one':5 'page':32 'separ':21 'simpl':2 'site':24 'static':10 'structur':26 'translat':18
16	27	23	en	Kami	'kami':1
7	28	23	en	interior	'interior':1
8	28	12	en	A room can feel completely different without changing its size. Sometimes the biggest difference comes from simple things: where the light falls, how objects are arranged, and how much empty space remains between them.	'arrang':26 'biggest':13 'chang':8 'come':15 'complet':5 'differ':6,14 'empti':30 'fall':22 'feel':4 'light':21 'much':29 'object':24 'remain':32 'room':2 'simpl':17 'size':10 'sometim':11 'space':31 'thing':18 'without':7
1	15	15	\N	Home hero	'hero':2 'home':1
2	15	9	en	Hello, Kami.A clean foundation for whatever comes next.Your new site is up and running. Add content, shape the structure, and keep building from here.	'add':15 'build':22 'clean':3 'come':7 'content':16 'foundat':4 'hello':1 'kami.a':2 'keep':21 'new':9 'next.your':8 'run':14 'shape':17 'site':10 'structur':19 'whatev':6
3	15	9	uk	Привіт, Камі.Чиста основа для всього, що буде далі.Твій новий сайт уже працює. Додавай контент, формуй структуру й розвивай його далі.	'буде':8 'всього':6 'далі':9,22 'для':5 'додавай':15 'й':19 'його':21 'камі':2 'контент':16 'новий':11 'основа':4 'працює':14 'привіт':1 'розвивай':20 'сайт':12 'структуру':18 'твій':10 'уже':13 'формуй':17 'чиста':3 'що':7
4	6	15	\N	intro	'intro':1
5	6	9	uk	Від простішого до складного - одна модель.Це статичний HTML-блок. Такі блоки можна створювати, редагувати й перекладати окремо від структури сайту, а потім додавати на сторінки в потрібних місцях.	'html':10 'html-блок':9 'а':23 'блок':11 'блоки':13 'в':28 'від':1,20 'до':3 'додавати':25 'й':17 'модель':6 'можна':14 'місцях':30 'на':26 'одна':5 'окремо':19 'перекладати':18 'потрібних':29 'потім':24 'простішого':2 'редагувати':16 'сайту':22 'складного':4 'статичний':8 'створювати':15 'сторінки':27 'структури':21 'такі':12 'це':7
56	28	24	uk	Світло, форма та простір	'простір':4 'світло':1 'та':3 'форма':2
9	28	13	en	Natural light makes surfaces, textures, and colors change throughout the day. A shape that feels flat in the morning may become the strongest element in the room by evening.Empty space matters just as much as the objects placed within it. Giving things room to breathe can make even a small space feel calmer and more deliberate.Good spaces rarely depend on a single dramatic feature. More often, they come from a balance of light, shape, texture, and the quiet areas in between.	'area':79C 'balanc':71C 'becom':21C 'breath':45C 'calmer':53C 'chang':8C 'color':7C 'come':68C 'day':11C 'deliberate.good':56C 'depend':59C 'dramat':63C 'element':24C 'even':48C 'evening.empty':29C 'featur':64C 'feel':15C,52C 'flat':16C 'give':41C 'light':2C,73C 'make':3C,47C 'matter':31C 'may':20C 'morn':19C 'much':34C 'natur':1C 'object':37C 'often':66C 'place':38C 'quiet':78C 'rare':58C 'room':27C,43C 'shape':13C,74C 'singl':62C 'small':50C 'space':30C,51C,57C 'strongest':23C 'surfac':4C 'textur':5C,75C 'thing':42C 'throughout':9C 'within':39C
10	28	24	en	Light, Shape and Space	'light':1 'shape':2 'space':4
11	31	12	en	This is a test category. You can rename and use it for your articles, or delete and make new ones.	'articl':14 'categori':5 'delet':16 'make':18 'new':19 'one':20 'renam':8 'test':4 'use':10
12	31	24	en	About everything	'everyth':2
13	30	12	en	Short description for article category here.\r\nCategory is an ordinary content item, so, you can edit, delete and create new categories in Content manager.	'articl':4 'categori':5,7,21 'content':11,23 'creat':19 'delet':17 'descript':2 'edit':16 'item':12 'manag':24 'new':20 'ordinari':10 'short':1
14	30	24	en	About Kami	'kami':2
15	27	23	en	Articles	'articl':1
17	27	12	en	An article is only one example of a content structure. Its fields can be added, removed, or changed to match the needs of a particular project.	'add':15 'articl':2 'chang':18 'content':9 'exampl':6 'field':12 'match':20 'need':22 'one':5 'particular':25 'project':26 'remov':16 'structur':10
18	27	13	en	Each field can have its own purpose and behavior: some values may be translated, while others can remain the same in every language. The template then decides how that content is presented on the page.The important part is the separation between content, structure, and presentation. You can change one without having to rebuild everything else around it.	'around':56C 'behavior':9C 'chang':48C 'content':30C,42C 'decid':27C 'els':55C 'everi':22C 'everyth':54C 'field':2C 'import':36C 'languag':23C 'may':12C 'one':49C 'other':16C 'page.the':35C 'part':37C 'present':32C,45C 'purpos':7C 'rebuild':53C 'remain':18C 'separ':40C 'structur':43C 'templat':25C 'translat':14C 'valu':11C 'without':50C
19	27	24	en	This Is Just an Article	'articl':5
20	5	19	\N	admin-sidebar	'admin':2 'admin-sidebar':1 'sidebar':3
21	13	24	en	Several content blocks in one grid	'block':3 'content':2 'grid':6 'one':5 'sever':1
22	13	15	\N	Content grid (2x2)	'2x2':3 'content':1 'grid':2
23	14	15	\N	Static top box	'box':3 'static':1 'top':2
24	14	9	en	Static pageDifferent templates for content blocksA content block can use any template provided by its plugin or overridden by the current theme. Changing the template changes how the content is presented without changing the content itself.	'block':8 'blocksa':6 'chang':23,26,33 'content':5,7,29,35 'current':21 'overridden':18 'pagediffer':2 'plugin':16 'present':31 'provid':13 'static':1 'templat':3,12,25 'theme':22 'use':10 'without':32
25	7	15	\N	what_is - 1	'1':3 'is':2 'what':1
26	7	9	en	Independent contentEach card in this grid is a separate content block. It can be edited and translated independently from the others.	'block':11 'card':3 'content':10 'contenteach':2 'edit':15 'grid':6 'independ':1,18 'other':21 'separ':9 'translat':17
27	8	15	\N	what_is - 2	'2':3 'is':2 'what':1
28	8	9	en	One groupThe group simply brings several blocks together. Here, its template renders them as a responsive grid.	'block':7 'bring':5 'grid':17 'group':3 'groupth':2 'one':1 'render':12 'respons':16 'sever':6 'simpli':4 'templat':11 'togeth':8
29	9	15	\N	what_is - 3	'3':3 'is':2 'what':1
30	9	9	en	Different presentationThe same content can use another template without changing the data stored inside the blocks.	'anoth':7 'block':16 'chang':10 'content':4 'data':12 'differ':1 'insid':14 'presentationth':2 'store':13 'templat':8 'use':6 'without':9
31	10	15	\N	what_is - 4	'4':3 'is':2 'what':1
32	10	9	en	Part of the pageThe page only decides where this group appears alongside other plugin instances. The content and its presentation remain separate.	'alongsid':12 'appear':11 'content':17 'decid':7 'group':10 'instanc':15 'page':5 'pageth':4 'part':1 'plugin':14 'present':20 'remain':21 'separ':22
33	16	15	\N	Static bottom box	'bottom':2 'box':3 'static':1
34	16	9	en	Nothing here requires a special page type. This page is simply assembled from reusable content blocks, a group, and a few templates.	'assembl':12 'block':16 'content':15 'group':18 'noth':1 'page':6,9 'requir':3 'reusabl':14 'simpli':11 'special':5 'templat':22 'type':7
35	43	15	\N	footer text	'footer':1 'text':2
36	43	9	uk	Powered by KamiCore	'by':2 'kamicore':3 'powered':1
37	43	9	en	Powered by KamiCore	'kamicor':3 'power':1
38	4	19	\N	user-context	'context':3 'user':2 'user-context':1
39	34	19	\N	user-sidebar	'sidebar':3 'user':2 'user-sidebar':1
40	29	23	en	interior	'interior':1
41	29	12	en	Improving a workspace does not always require new furniture or a complete redesign. A few small changes can often make it more comfortable and easier to use.	'alway':6 'chang':17 'comfort':23 'complet':12 'easier':25 'furnitur':9 'improv':1 'make':20 'new':8 'often':19 'redesign':13 'requir':7 'small':16 'use':27 'workspac':3
42	29	13	en	Remove what you do not needKeep the things you use regularly within reach and move everything else out of the way. A little more free space can make the whole workspace feel less distracting.Improve the lightGood lighting makes long periods of work easier. Use natural light when possible, and place additional lighting where it helps without creating glare or harsh shadows.Leave room for changeA workspace does not have to be finished forever. Keep enough flexibility to move things around, add something useful, or remove something that no longer works for you.Small adjustments are easy to make, easy to reverse, and often enough to noticeably change how a space feels.	'add':80C 'addit':51C 'adjust':92C 'around':79C 'chang':105C 'changea':64C 'creat':57C 'distracting.improve':34C 'easi':94C,97C 'easier':43C 'els':17C 'enough':74C,102C 'everyth':16C 'feel':32C,109C 'finish':71C 'flexibl':75C 'forev':72C 'free':25C 'glare':58C 'harsh':60C 'help':55C 'keep':73C 'less':33C 'light':37C,46C,52C 'lightgood':36C 'littl':23C 'long':39C 'longer':88C 'make':28C,38C,96C 'move':15C,77C 'natur':45C 'needkeep':6C 'notic':104C 'often':101C 'period':40C 'place':50C 'possibl':48C 'reach':13C 'regular':11C 'remov':1C,84C 'revers':99C 'room':62C 'shadows.leave':61C 'someth':81C,85C 'space':26C,108C 'thing':8C,78C 'use':10C,44C,82C 'way':21C 'whole':30C 'within':12C 'without':56C 'work':42C,89C 'workspac':31C,65C 'you.small':91C
43	29	24	en	Three Small Changes	'chang':3 'small':2 'three':1
44	29	23	uk	interior	'interior':1
45	29	12	uk	Щоб поліпшити робоче місце, не завжди потрібні нові меблі чи повне перепланування. Часто достатньо кількох невеликих змін, щоб зробити його комфортнішим і зручнішим у використанні.	'використанні':25 'достатньо':14 'завжди':6 'змін':17 'зробити':19 'зручнішим':23 'його':20 'комфортнішим':21 'кількох':15 'меблі':9 'місце':4 'не':5 'невеликих':16 'нові':8 'перепланування':12 'повне':11 'поліпшити':2 'потрібні':7 'робоче':3 'у':24 'часто':13 'чи':10 'щоб':1,18 'і':22
46	29	13	uk	Приберіть те, що вам не потрібноТримайте речі, якими користуєтеся регулярно, під рукою, а все інше приберіть з дороги. Трохи більше вільного простору допоможе зменшити відволікаючі фактори на робочому місці.Покращіть освітленняХороше освітлення полегшує тривалу роботу. По можливості використовуйте природне світло, а додаткове освітлення розміщуйте там, де воно буде корисним, не створюючи відблисків чи різких тіней.Залиште місце для змінРобоче місце не має бути остаточно сформованим. Залишайте достатньо гнучкості, щоб переставляти речі, додавати щось корисне або прибирати те, що вам більше не підходить.Невеликі зміни легко внести, легко скасувати, і часто їх достатньо, щоб помітно змінити атмосферу приміщення.	'а':13C,41C 'або':75C 'атмосферу':96C 'буде':48C 'бути':63C 'більше':20C,80C 'вам':4C,79C 'використовуйте':38C 'внести':86C 'воно':47C 'все':14C 'відблисків':52C 'відволікаючі':25C 'вільного':21C 'гнучкості':68C 'де':46C 'для':58C 'додавати':72C 'додаткове':42C 'допоможе':23C 'дороги':18C 'достатньо':67C,92C 'з':17C 'залишайте':66C 'залиште':56C 'зменшити':24C 'зміни':84C 'змінити':95C 'змінробоче':59C 'корисне':74C 'корисним':49C 'користуєтеся':9C 'легко':85C,87C 'має':62C 'можливості':37C 'місце':57C,60C 'місці':29C 'на':27C 'не':5C,50C,61C,81C 'невеликі':83C 'освітлення':32C,43C 'освітленняхороше':31C 'остаточно':64C 'переставляти':70C 'по':36C 'покращіть':30C 'полегшує':33C 'помітно':94C 'потрібнотримайте':6C 'приберіть':1C,16C 'прибирати':76C 'приміщення':97C 'природне':39C 'простору':22C 'під':11C 'підходить':82C 'регулярно':10C 'речі':7C,71C 'роботу':35C 'робочому':28C 'розміщуйте':44C 'рукою':12C 'різких':54C 'світло':40C 'скасувати':88C 'створюючи':51C 'сформованим':65C 'там':45C 'те':2C,77C 'тривалу':34C 'трохи':19C 'тіней':55C 'фактори':26C 'часто':90C 'чи':53C 'що':3C,78C 'щоб':69C,93C 'щось':73C 'якими':8C 'і':89C 'інше':15C 'їх':91C
47	29	24	uk	Три невеликі зміни	'зміни':3 'невеликі':2 'три':1
48	27	23	uk	Articles	'articles':1
49	27	23	uk	Kami	'kami':1
50	27	12	uk	Стаття - це лише один приклад структури контенту. Ви можете змінювати, видаляти чи додавати поля в залежності від потреб конкретного проєкту.	'в':15 'ви':8 'видаляти':11 'від':17 'додавати':13 'залежності':16 'змінювати':10 'конкретного':19 'контенту':7 'лише':3 'можете':9 'один':4 'поля':14 'потреб':18 'приклад':5 'проєкту':20 'стаття':1 'структури':6 'це':2 'чи':12
51	27	13	uk	Кожне поле може мати своє призначення та особливості поведінки: деякі значення можуть перекладатися, тоді як інші залишаються незмінними в усіх мовах. Шаблон визначає, як саме цей вміст відображається на сторінці.Головне - це розділення вмісту, структури та оформлення. Ви можете змінювати одне з них, не переробляючи все інше навколо нього.	'в':19C 'ви':38C 'визначає':23C 'вміст':27C 'вмісту':34C 'все':46C 'відображається':28C 'головне':31C 'деякі':10C 'з':42C 'залишаються':17C 'змінювати':40C 'значення':11C 'кожне':1C 'мати':4C 'мовах':21C 'може':3C 'можете':39C 'можуть':12C 'на':29C 'навколо':48C 'не':44C 'незмінними':18C 'них':43C 'нього':49C 'одне':41C 'особливості':8C 'оформлення':37C 'перекладатися':13C 'переробляючи':45C 'поведінки':9C 'поле':2C 'призначення':6C 'розділення':33C 'саме':25C 'своє':5C 'сторінці':30C 'структури':35C 'та':7C,36C 'тоді':14C 'усіх':20C 'це':32C 'цей':26C 'шаблон':22C 'як':15C,24C 'інше':47C 'інші':16C
52	27	24	uk	Це просто стаття	'просто':2 'стаття':3 'це':1
53	28	23	uk	interior	'interior':1
54	28	12	uk	Кімната може виглядати зовсім інакше, навіть якщо її розміри не змінилися. Іноді найбільшу різницю створюють прості речі: те, куди падає світло, як розташовані предмети та скільки вільного простору залишається між ними.	'виглядати':3 'вільного':27 'залишається':29 'змінилися':11 'зовсім':4 'куди':19 'кімната':1 'може':2 'між':30 'навіть':6 'найбільшу':13 'не':10 'ними':31 'падає':20 'предмети':24 'простору':28 'прості':16 'речі':17 'розміри':9 'розташовані':23 'різницю':14 'світло':21 'скільки':26 'створюють':15 'та':25 'те':18 'як':22 'якщо':7 'інакше':5 'іноді':12 'її':8
55	28	13	uk	Природне світло змушує поверхні, текстури та кольори змінюватися протягом дня. Форма, яка вранці здається плоскою, до вечора може стати найвиразнішим елементом у кімнаті.Порожній простір має таке ж значення, як і предмети, розміщені в ньому. Якщо дати речам простір для «дихання», навіть невелика кімната може здаватися спокійнішою та більш продуманою.Гарні інтер’єри рідко залежать від якоїсь однієї ефектної деталі. Найчастіше їхня краса полягає у балансі світла, форм, текстур та спокійних зон між ними.	'балансі':66C 'більш':49C 'в':34C 'вечора':17C 'вранці':13C 'від':56C 'гарні':51C 'дати':37C 'деталі':60C 'дихання':41C 'для':40C 'дня':10C 'до':16C 'елементом':21C 'ефектної':59C 'ж':28C 'залежать':55C 'здаватися':46C 'здається':14C 'змушує':3C 'змінюватися':8C 'значення':29C 'зон':72C 'кольори':7C 'краса':63C 'кімната':44C 'кімнаті':23C 'має':26C 'може':18C,45C 'між':73C 'навіть':42C 'найвиразнішим':20C 'найчастіше':61C 'невелика':43C 'ними':74C 'ньому':35C 'однієї':58C 'плоскою':15C 'поверхні':4C 'полягає':64C 'порожній':24C 'предмети':32C 'природне':1C 'продуманою':50C 'простір':25C,39C 'протягом':9C 'речам':38C 'розміщені':33C 'рідко':54C 'світла':67C 'світло':2C 'спокійних':71C 'спокійнішою':47C 'стати':19C 'та':6C,48C,70C 'таке':27C 'текстур':69C 'текстури':5C 'у':22C,65C 'форм':68C 'форма':11C 'як':30C 'яка':12C 'якоїсь':57C 'якщо':36C 'єри':53C 'і':31C 'інтер':52C 'їхня':62C
57	7	9	uk	Незалежний контентКожна картка в цій таблиці є окремим блоком контенту. Її можна редагувати та перекладати незалежно від інших.	'блоком':9 'в':4 'від':17 'картка':3 'контенткожна':2 'контенту':10 'можна':12 'незалежний':1 'незалежно':16 'окремим':8 'перекладати':15 'редагувати':13 'та':14 'таблиці':6 'цій':5 'є':7 'інших':18 'її':11
58	8	9	uk	Одна групаГрупа просто об’єднує кілька блоків. У цьому випадку її шаблон відображає їх у вигляді адаптивної сітки.	'адаптивної':17 'блоків':7 'вигляді':16 'випадку':10 'відображає':13 'групагрупа':2 'кілька':6 'об':4 'одна':1 'просто':3 'сітки':18 'у':8,15 'цьому':9 'шаблон':12 'єднує':5 'їх':14 'її':11
59	9	9	uk	Інший варіант оформленняДля одного й того ж вмісту можна використовувати інший шаблон, не змінюючи дані, що зберігаються в блоках.	'блоках':19 'в':18 'варіант':2 'використовувати':10 'вмісту':8 'дані':15 'ж':7 'зберігаються':17 'змінюючи':14 'й':5 'можна':9 'не':13 'одного':4 'оформленнядля':3 'того':6 'шаблон':12 'що':16 'інший':1,11
60	10	9	uk	Частина сторінкиСторінка лише визначає, де ця група відображатиметься поряд з іншими екземплярами плагіна. Вміст та його оформлення залишаються окремими.	'визначає':4 'вміст':14 'відображатиметься':8 'група':7 'де':5 'екземплярами':12 'з':10 'залишаються':18 'його':16 'лише':3 'окремими':19 'оформлення':17 'плагіна':13 'поряд':9 'сторінкисторінка':2 'та':15 'ця':6 'частина':1 'іншими':11
61	14	9	uk	Статична сторінкаРізні шаблони для блоків вмістуБлок вмісту може використовувати будь-який шаблон, наданий його плагіном або замінений поточною темою. Зміна шаблону змінює спосіб відображення вмісту, не змінюючи сам вміст.	'або':17 'блоків':5 'будь':11 'будь-який':10 'використовувати':9 'вміст':30 'вмісту':7,26 'вмістублок':6 'відображення':25 'для':4 'замінений':18 'зміна':21 'змінюючи':28 'змінює':23 'його':15 'може':8 'наданий':14 'не':27 'плагіном':16 'поточною':19 'сам':29 'спосіб':24 'статична':1 'сторінкарізні':2 'темою':20 'шаблон':13 'шаблони':3 'шаблону':22 'який':12
62	16	9	uk	Тут нічого не вимагає використання спеціального типу сторінки. Ця сторінка просто складена з блоків контенту, що можна використовувати повторно, групи та кількох шаблонів.	'блоків':14 'використання':5 'використовувати':18 'вимагає':4 'групи':20 'з':13 'контенту':15 'кількох':22 'можна':17 'не':3 'нічого':2 'повторно':19 'просто':11 'складена':12 'спеціального':6 'сторінка':10 'сторінки':8 'та':21 'типу':7 'тут':1 'ця':9 'шаблонів':23 'що':16
63	1	19	\N	front-nav	'front':2 'front-nav':1 'nav':3
\.


--
-- Data for Name: languages; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.languages (lang_code, lang_name, is_active, cfg_name) FROM stdin;
en	English	t	english
es	Spanish	f	spanish
fr	French	f	french
de	German	f	german
pt	Portuguese	f	portuguese
ru	Russian	f	russian
uk	Ukrainian	t	simple
pl	Polish	f	simple
it	Italian	f	italian
nl	Dutch	f	dutch
sv	Swedish	f	swedish
no	Norwegian	f	norwegian
da	Danish	f	danish
fi	Finnish	f	finnish
cs	Czech	f	simple
sk	Slovak	f	simple
hu	Hungarian	f	hungarian
ro	Romanian	f	romanian
bg	Bulgarian	f	simple
sr	Serbian	f	simple
hr	Croatian	f	simple
sl	Slovenian	f	simple
el	Greek	f	greek
tr	Turkish	f	turkish
ar	Arabic	f	arabic
he	Hebrew	f	simple
hi	Hindi	f	simple
zh	Chinese	f	simple
ja	Japanese	f	simple
ko	Korean	f	simple
\.


--
-- Data for Name: logs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.logs (rec_id, log_level, log_message, log_context, created_at) FROM stdin;
\.


--
-- Data for Name: media; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.media (media_id, file_path, uploaded_by, domain_id, content_type, created_at) FROM stdin;
\.


--
-- Data for Name: mime_groups; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.mime_groups (mime_group_id, mime_group_uuid, mime_group_code, title, description, is_system, is_enabled, created_at) FROM stdin;
\.


--
-- Data for Name: media_acl; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.media_acl (domain_id, usergroup_id, mime_group_id, can_view, can_upload, can_delete, created_at) FROM stdin;
\.


--
-- Data for Name: mime_group_mimes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.mime_group_mimes (mime_group_id, mime, ext, is_enabled, created_at) FROM stdin;
\.


--
-- Data for Name: notification_messages; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.notification_messages (notification_id, context_id, user_id, text, style, created_at, expires_at) FROM stdin;
\.


--
-- Data for Name: pages; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.pages (page_id, domain_id, uuid, system_name, page_slug, page_settings, page_plugins, layout_id, parent_id) FROM stdin;
1	1	4e8b7b25-7777-4974-892f-a56fd33681d0	Home	/	[]	{"top_plugins": [{"Navigation": {"handler": "view", "menu_id": "1", "template": "topnav"}}, {"LangSwitcher": {"handler": "view", "template": "simple"}}, {"UserProfile": {"handler": "view"}}], "head_plugins": [], "hero_plugins": [{"ViewStatic": {"handler": "view", "item_id": "15"}}], "footer_plugins": [{"ViewStatic": {"handler": "view", "item_id": "43"}}, {"Notifications": {"handler": "view"}}], "content_plugins": [{"ViewStatic": {"handler": "view", "item_id": "6"}}, {"ViewArticles": {"handler": "list", "items_per_page": "1", "show_pagination": "1", "articles_category_ids": ["30"]}}]}	1	\N
2	1	041cb374-f326-4c70-9d40-b0b113951ef3	Admin	admin	[]	{"sidebar": [{"Navigation": {"handler": "view", "menu_id": "5", "template": "sidebar"}}], "content_top": [{"LangSwitcher": {"handler": "view", "template": "simple"}}, {"UserProfile": {"handler": "view"}}], "head_plugins": [], "content_middle": [], "content_bottoms": [{"ViewStatic": {"handler": "view", "item_id": "43"}}]}	3	\N
6	1	842fc2e0-20f9-427e-aaa3-aaa245e473bc	User account	account	[]	{"top_plugins": [{"Navigation": {"handler": "view", "menu_id": "1", "template": "topnav"}}, {"LangSwitcher": {"handler": "view", "template": "simple"}}, {"UserProfile": {"handler": "view"}}], "head_plugins": [], "footer_plugins": [{"ViewStatic": {"handler": "view", "item_id": "43"}}], "content_plugins": [{"UserProfile": {"handler": "account"}}], "sidebar_plugins": [{"Navigation": {"handler": "view", "menu_id": "34", "template": "sidebar"}}]}	4	\N
15	1	80229faa-4284-4d7c-90c8-b2403e906836	Static page	static-page	[]	{"top_plugins": [{"Navigation": {"handler": "view", "menu_id": "1", "template": "topnav"}}, {"LangSwitcher": {"handler": "view", "template": "simple"}}, {"UserProfile": {"handler": "view"}}], "head_plugins": [], "footer_plugins": [{"ViewStatic": {"handler": "view", "item_id": "43"}}, {"Notifications": {"handler": "view"}}], "content_plugins": [{"ViewStatic": {"handler": "view", "item_id": "14"}}, {"ViewStatic": {"handler": "view", "item_id": "13"}}, {"ViewStatic": {"handler": "view", "item_id": "16"}}]}	2	\N
16	1	6925f488-c5e0-4bd2-9439-ef53b7a3f61f	Blog	blog	[]	{"top_plugins":[{"Navigation":{"handler":"view","menu_id":"1","template":"topnav"}},{"LangSwitcher":{"handler":"view","template":"simple"}},{"UserProfile":{"handler":"view"}}],"head_plugins":[],"footer_plugins":[{"ViewStatic":{"handler":"view","item_id":"43"}},{"Notifications":{"handler":"view"}}],"content_plugins":[{"Breadcrumbs":{"handler":"view"}},{"ViewArticles":{"handler":"list","items_per_page":"","show_pagination":"1","articles_category_ids":[]}}]}	2	\N
17	1	5fc12690-99a0-4c13-9f1e-d4838507fad9	API settings	account-api	[]	{"top_plugins": [{"Navigation": {"handler": "view", "menu_id": "1", "template": "topnav"}}, {"LangSwitcher": {"handler": "view", "template": "simple"}}, {"UserProfile": {"handler": "view"}}], "head_plugins": [], "footer_plugins": [{"ViewStatic": {"handler": "view", "item_id": "43"}}, {"Notifications": {"handler": "view"}}], "content_plugins": [{"ApiAccess": {"handler": "manage"}}], "sidebar_plugins": [{"Navigation": {"handler": "view", "menu_id": "34", "template": "sidebar"}}]}	4	\N
13	1	dc390832-8af1-4700-8902-b02758820631	теми	admin-themes	[]	{"sidebar": [{"Navigation": {"handler": "view", "menu_id": "5", "template": "sidebar"}}], "content_top": [{"UserProfile": {"handler": "view"}}, {"LangSwitcher": {"handler": "view", "template": "simple"}}], "head_plugins": [], "content_middle": [{"ThemeManager": {"handler": "manage"}}], "content_bottoms": [{"ViewStatic": {"handler": "view", "item_id": "43"}}]}	3	2
12	1	5cc03c17-3833-4003-9d25-13fdd5f641cd	Медіа	admin-media	[]	{"sidebar": [{"Navigation": {"handler": "view", "menu_id": "5", "template": "sidebar"}}], "content_top": [{"LangSwitcher": {"handler": "view", "template": "simple"}}, {"UserProfile": {"handler": "view"}}], "head_plugins": [], "content_middle": [{"Media": {"handler": "view"}}], "content_bottoms": [{"ViewStatic": {"handler": "view", "item_id": "43"}}]}	3	2
11	1	c6b29462-4207-45db-be29-9579d9521d5f	users	admin-users	[]	{"sidebar": [{"Navigation": {"handler": "view", "menu_id": "5", "template": "sidebar"}}], "content_top": [{"LangSwitcher": {"handler": "view", "template": "simple"}}, {"UserProfile": {"handler": "view"}}], "head_plugins": [], "content_middle": [{"UserManager": {"handler": "manage"}}], "content_bottoms": [{"ViewStatic": {"handler": "view", "item_id": "43"}}]}	3	2
10	1	11abbb1e-46b8-48c9-8bf0-7a401359ee07	system	admin-system	[]	{"sidebar": [{"Navigation": {"handler": "view", "menu_id": "5", "template": "sidebar"}}], "content_top": [{"UserProfile": {"handler": "view"}}, {"LangSwitcher": {"handler": "view", "template": "simple"}}], "head_plugins": [], "content_middle": [{"SystemManager": {"handler": "manage"}}], "content_bottoms": [{"ViewStatic": {"handler": "view", "item_id": "43"}}]}	3	2
9	1	6c74d3b1-7bf2-4186-a592-c41663bf7fdd	translations	admin-translations	[]	{"sidebar": [{"Navigation": {"handler": "view", "menu_id": "5", "template": "sidebar"}}], "content_top": [{"LangSwitcher": {"handler": "view", "template": "simple"}}, {"UserProfile": {"handler": "view"}}], "head_plugins": [], "content_middle": [{"TranslationManager": {"handler": "manage"}}], "content_bottoms": [{"ViewStatic": {"handler": "view", "item_id": "43"}}]}	3	2
8	1	2a56244f-9f40-4ac6-b24e-311f21bdcfae	Plugin manager	admin-plugins	[]	{"sidebar": [{"Navigation": {"handler": "view", "menu_id": "5", "template": "sidebar"}}], "content_top": [{"LangSwitcher": {"handler": "view", "template": "simple"}}, {"UserProfile": {"handler": "view"}}], "head_plugins": [], "content_middle": [{"PluginManager": {"handler": "manage"}}], "content_bottoms": [{"ViewStatic": {"handler": "view", "item_id": "43"}}]}	3	2
5	1	ff959899-1251-4b1d-81c2-8563f24ca150	Content manager	admin-content	[]	{"sidebar": [{"Navigation": {"handler": "view", "menu_id": "5", "template": "sidebar"}}], "content_top": [{"LangSwitcher": {"handler": "view", "template": "simple"}}, {"UserProfile": {"handler": "view"}}], "head_plugins": [], "content_middle": [{"ContentManager": {"handler": "manage"}}], "content_bottoms": [{"ViewStatic": {"handler": "view", "item_id": "43"}}]}	3	2
4	1	f2d5f754-cbff-4e6c-a945-38d229f4346e	Admin-pages	admin-pages	[]	{"sidebar": [{"Navigation": {"handler": "view", "menu_id": "5", "template": "sidebar"}}], "content_top": [{"LangSwitcher": {"handler": "view", "template": "simple"}}, {"UserProfile": {"handler": "view"}}], "head_plugins": [], "content_middle": [{"PageManager": {"handler": "manage"}}], "content_bottoms": [{"ViewStatic": {"handler": "view", "item_id": "43"}}]}	3	2
3	1	b4d620ee-9c26-4bc8-9694-a2ca59db928f	Admin-navigation	admin-nav	[]	{"sidebar": [{"Navigation": {"handler": "view", "menu_id": "5", "template": "sidebar"}}], "content_top": [{"LangSwitcher": {"handler": "view", "template": "simple"}}, {"UserProfile": {"handler": "view"}}], "head_plugins": [], "content_middle": [{"Navigation": {"handler": "manage"}}], "content_bottoms": [{"ViewStatic": {"handler": "view", "item_id": "43"}}]}	3	2
14	1	e16492a6-3ae9-43b0-9bce-742713ef4ac3	About Everything	about-everything	[]	{"top_plugins":[{"Navigation":{"handler":"view","menu_id":"1","template":"topnav"}},{"LangSwitcher":{"handler":"view","template":"simple"}},{"UserProfile":{"handler":"view"}}],"head_plugins":[],"footer_plugins":[{"ViewStatic":{"handler":"view","item_id":"43"}},{"Notifications":{"handler":"view"}}],"content_plugins":[{"Breadcrumbs":{"handler":"view"}},{"ViewArticles":{"handler":"view","items_per_page":"","show_pagination":"1","articles_category_ids":["31"]}}]}	2	16
7	1	5b786a30-fc2c-43f3-8551-c4c89a1fd425	About Kami	about-kami	[]	{"top_plugins":[{"Navigation":{"handler":"view","menu_id":"1","template":"topnav"}},{"LangSwitcher":{"handler":"view","template":"simple"}},{"UserProfile":{"handler":"view"}}],"head_plugins":[],"footer_plugins":[{"ViewStatic":{"handler":"view","item_id":"43"}},{"Notifications":{"handler":"view"}}],"content_plugins":[{"Breadcrumbs":{"handler":"view"}},{"ViewArticles":{"handler":"view","items_per_page":"","show_pagination":"1","articles_category_ids":["30"]}}]}	2	16
\.


--
-- Data for Name: page_acl; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.page_acl (page_id, usergroup_id, created_at) FROM stdin;
1	2	2026-09-03 16:51:26.557351
1	3	2026-09-03 16:50:19.520598
1	4	2026-09-03 16:50:57.557202
6	2	2026-09-03 16:51:26.557351
6	4	2026-09-03 16:50:57.557202
7	2	2026-09-03 16:51:26.557351
7	3	2026-09-03 16:50:19.520598
7	4	2026-09-03 16:50:57.557202
14	2	2026-09-03 16:51:26.557351
14	3	2026-09-03 16:50:19.520598
14	4	2026-09-03 16:50:57.557202
15	2	2026-09-03 16:51:26.557351
15	3	2026-09-03 16:50:19.520598
15	4	2026-09-03 16:50:57.557202
16	2	2026-09-03 16:51:26.557351
16	3	2026-09-03 16:50:19.520598
16	4	2026-09-03 16:50:57.557202
17	4	2026-09-03 16:50:57.557202
\.


--
-- Data for Name: pgm_recipes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.pgm_recipes (recipe_id, recipe_uuid, recipe_key, name, description, payload, created_at, updated_at) FROM stdin;
1	f31b3114-f367-402c-8cf7-f45cddbe58ba	admin	Admin page	\N	{"layout": "admin", "wrappers": {"sidebar": [{"plugin": "Navigation", "handler": "view", "instance_params": {"menu_id": "5", "template": "sidebar"}}], "content_top": [{"plugin": "LangSwitcher", "handler": "view", "instance_params": {"template": "simple"}}, {"plugin": "UserProfile", "handler": "view", "instance_params": []}], "content_middle": [], "content_bottoms": [{"plugin": "Notifications", "handler": "view", "instance_params": []}, {"plugin": "ViewStatic", "handler": "view", "instance_params": {"handler": "view", "item_id": "43"}}]}, "page_prefix": "admin-", "default_navigation_menus": ["admin-sidebar"]}	2026-08-21 23:17:46.153019	2026-09-05 14:01:24
2	90e7edc3-21fb-4e6c-b3d4-65884973e9b1	normal	Normal page	Simple front-end page	{"layout":"simple","wrappers":{"top_plugins":[{"plugin":"Navigation","handler":"view","instance_params":{"menu_id":"1","template":"topnav"}},{"plugin":"LangSwitcher","handler":"view","instance_params":{"template":"simple"}},{"plugin":"UserProfile","handler":"view","instance_params":[]}],"head_plugins":[],"footer_plugins":[{"plugin":"Notifications","handler":"view","instance_params":[]},{"plugin":"ViewStatic","handler":"view","instance_params":{"handler":"view","item_id":"43"}}],"content_plugins":[{"plugin":"Breadcrumbs","handler":"view","instance_params":[]}]},"page_prefix":"","default_navigation_menus":["front-nav"]}	2026-08-31 16:59:28.711766	2026-09-05 14:03:21
3	afb36ff1-d1de-463b-b0c4-81f9cb462d0f	user	User account page	Page with sidebar menu for user area	{"layout": "sidebar", "wrappers": {"top_plugins": [{"plugin": "Navigation", "handler": "view", "instance_params": {"menu_id": "1", "template": "topnav"}}, {"plugin": "LangSwitcher", "handler": "view", "instance_params": {"template": "simple"}}, {"plugin": "UserProfile", "handler": "view", "instance_params": []}], "head_plugins": [], "footer_plugins": [{"plugin": "Notifications", "handler": "view", "instance_params": []}, {"plugin": "ViewStatic", "handler": "view", "instance_params": {"handler": "view", "item_id": "43"}}], "content_plugins": [], "sidebar_plugins": [{"plugin": "Navigation", "handler": "view", "instance_params": {"menu_id": "34", "template": "sidebar"}}]}, "page_prefix": "account-", "default_navigation_menus": ["user-sidebar"]}	2026-09-03 15:14:54.026088	2026-09-05 14:00:22
\.


--
-- Data for Name: plugin_acl; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.plugin_acl (plugin_acl_id, usergroup_id, plugin_id, handler, created_at) FROM stdin;
1	1	18	view	2026-08-31 13:05:14.152573
2	3	19	list	2026-09-03 16:50:19.520598
3	3	19	view	2026-09-03 16:50:19.520598
4	3	9	view	2026-09-03 16:50:19.520598
5	3	1	view	2026-09-03 16:50:19.520598
6	3	18	view	2026-09-03 16:50:19.520598
7	3	5	view	2026-09-03 16:50:19.520598
8	3	7	view	2026-09-03 16:50:19.520598
9	3	6	view	2026-09-03 16:50:19.520598
10	4	21	manage	2026-09-03 16:50:57.557202
11	4	19	list	2026-09-03 16:50:57.557202
12	4	4	view	2026-09-03 16:50:57.557202
13	4	9	view	2026-09-03 16:50:57.557202
14	4	1	view	2026-09-03 16:50:57.557202
15	4	5	view	2026-09-03 16:50:57.557202
16	4	7	view	2026-09-03 16:50:57.557202
17	4	6	view	2026-09-03 16:50:57.557202
18	4	6	account	2026-09-03 16:50:57.557202
19	2	19	list	2026-09-03 16:51:26.557351
20	2	19	view	2026-09-03 16:51:26.557351
21	2	4	view	2026-09-03 16:51:26.557351
22	2	9	view	2026-09-03 16:51:26.557351
23	2	1	view	2026-09-03 16:51:26.557351
24	2	18	view	2026-09-03 16:51:26.557351
25	2	5	view	2026-09-03 16:51:26.557351
26	2	7	view	2026-09-03 16:51:26.557351
27	2	6	view	2026-09-03 16:51:26.557351
28	2	6	account	2026-09-03 16:51:26.557351
29	1	21	manage	2026-09-05 05:16:51.640732
30	2	22	view	2026-09-12 00:00:00
31	3	22	view	2026-09-12 00:00:00
\.


--
-- Data for Name: plugin_domains; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.plugin_domains (plugin_id, domain_id, local_settings) FROM stdin;
7	1	{}
17	1	{}
1	1	\N
2	1	{}
3	1	\N
4	1	\N
5	1	\N
6	1	\N
8	1	\N
9	1	\N
10	1	{}
11	1	{}
12	1	{}
13	1	{}
14	1	{}
15	1	{}
16	1	{}
18	1	{"expire": 600}
19	1	{"default_count": 20, "items_count_values": [10, 20, 50, 0], "items_count_selector": 1}
20	1	{}
21	1	{}
22	1	{}
\.


--
-- Data for Name: plugin_endpoints; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.plugin_endpoints (endpoint_id, plugin_id, endpoint, route_method) FROM stdin;
1	7	auth	routeAuth
\.


--
-- Data for Name: core_migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.core_migrations (migration_name, checksum, applied_at) FROM stdin;
001_compound_field_type.sql	7adabcf0de2aa39fa74ecea0a12d63905ed973b09ef35e4ebdc07b54b1bbf4b0	2026-09-12 00:00:00
\.


--
-- Data for Name: plugin_migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.plugin_migrations (plugin_id, migration_name, checksum, applied_at) FROM stdin;
1	001_restore_navmenu_translations.sql	92194c3608f112c28b7df4e5225da9c544c13250c0aec2653a400bba4d61d22e	2026-09-02 14:59:07.466241
3	001_recipes.sql	634f3e442f63979444d69c4c797d90926f7f0bfe30537741f21cbdf929abd9c7	2026-08-23 09:14:30.341208
6	001_default_account_acl.sql	a6cbe266c51f08700514458a628d304433865bab87162297e0c2f26fb0cb1efd	2026-08-27 16:05:42.264949
7	001_auth_identities.sql	8e845b39f341793561a273b331b47e65089cc6bfc26207c917e89717830af0c9	2026-08-26 10:48:07.47066
7	002_remove_verification_page_setting.sql	463ca34f25faa6c470cddee65f8a2dba07565da335719f9df306b103d53ac9c1	2026-08-26 17:07:27.336953
7	003_remove_password_reset_page_setting.sql	71a7d329e71772ea3385a86969ba4bfacd1e5f4d7dd9de861c2d09c44c2c8ebe	2026-08-26 18:58:42.845391
7	004_one_identity_per_provider.sql	36867d8d9353716cd3a2ed851bca6919341f91ca94a154ba60e7b6e520b9eba9	2026-08-27 15:22:35.273502
7	005_credential_management.sql	723d7deda3f8ca3f4537ff599d7ad7fc782a96e8bdee833ef3357d3c88bf33d8	2026-08-27 15:28:25.447365
10	001_initial.sql	f57c6d4039c7b8c1f00ad70270e1516292ab253ff24b1af5c7e336683c76aee3	2026-08-20 15:13:57.288132
10	002_move_recipes_to_page_manager.sql	a56d402d56df48469a9c99d94f0b369fa94c4022ed3465e4c771f91fea00b3db	2026-08-23 09:14:30.347527
13	001_global_settings.sql	d532db290b369ab7761fcd4425508131ae7ea9ad326f4ebae1d0b062d85f5540	2026-08-22 10:09:46.720575
18	001_initial.sql	01a4c201b3d0b0a35a92820acb6d4225f7844e64b32472d18b83072199df7f65	2026-08-26 16:30:53.268997
18	002_default_view_acl.sql	596ccd47ac1d847b49380a068e17fd4485347561c3bf658c35076d1c05aabe8c	2026-08-31 13:05:14.152573
18	003_context_id.sql	09a37d7d4843a401b347f8115abd31a1a0542d31427131ae5da25b25b98e639c	2026-09-11 12:15:37.681305
21	001_default_manage_acl.sql	35b456fa9dd60f893f26d7850600afc91f1c465407bafce71a940a89a72405ea	2026-09-02 14:03:19.029886
\.


--
-- Data for Name: pm_setup_history; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.pm_setup_history (setup_id, plugin_id, plugin_system_name, domain_id, action, status, preset_name, config, error, created_at) FROM stdin;
1	11	TextProcessor	\N	install	success	\N	{"reference": "TextProcessor"}	\N	2026-08-21 23:04:35.673727
2	12	TranslationManager	\N	install	success	\N	{"reference": "TranslationManager"}	\N	2026-08-21 23:22:45.869346
3	12	TranslationManager	1	setup	success	standard	{"resolved": {"pages": [{"title": "Translations", "issues": [], "titles": {"en": "Translations", "uk": "Переклади"}, "resolved": {"slug": "admin-translations", "layout": "admin", "recipe_id": 1, "recipe_name": "admin", "system_name": "translations"}, "requested": {"slug": "translations", "recipe_name": "admin", "system_name": "translations"}, "navigation": [{"menu_id": 5, "resolved": "sidebar_nav", "requested": "sidebar_nav"}], "resource_key": "page:0", "recipe_snapshot": {"name": "Admin page", "payload": {"layout": "admin", "wrappers": [], "page_prefix": "admin-", "default_navigation_menus": ["sidebar_nav"]}, "recipe_id": 1, "created_at": "2026-08-21 23:17:46.153019", "recipe_key": "admin", "updated_at": "2026-08-21 23:32:46", "description": null, "recipe_uuid": "f31b3114-f367-402c-8cf7-f45cddbe58ba"}, "preset_instances": [{"skip": false, "plugin": "TranslationManager", "wrapper": "content_middle", "requested": {"action": "default", "handler": "manage", "instance_params": []}, "instance_params": {"action": "default", "handler": "manage"}}], "recipe_instances": []}], "valid": true, "plugin": "TranslationManager", "preset": "standard", "domain_id": 1, "default_language": "en"}, "requested": {"pages": [{"title": "Translations", "issues": [], "titles": {"en": "Translations", "uk": "Переклади"}, "resolved": {"slug": "admin-translations", "layout": "admin", "recipe_id": 1, "recipe_name": "admin", "system_name": "translations"}, "requested": {"slug": "translations", "recipe_name": "admin", "system_name": "translations"}, "navigation": [{"menu_id": 5, "resolved": "sidebar_nav", "requested": "sidebar_nav"}], "resource_key": "page:0", "recipe_snapshot": {"name": "Admin page", "payload": {"layout": "admin", "wrappers": [], "page_prefix": "admin-", "default_navigation_menus": ["sidebar_nav"]}, "recipe_id": 1, "created_at": "2026-08-21 23:17:46.153019", "recipe_key": "admin", "updated_at": "2026-08-21 23:32:46", "description": null, "recipe_uuid": "f31b3114-f367-402c-8cf7-f45cddbe58ba"}, "preset_instances": [{"skip": false, "plugin": "TranslationManager", "wrapper": "content_middle", "requested": {"action": "default", "handler": "manage", "instance_params": []}, "instance_params": {"action": "default", "handler": "manage"}}], "recipe_instances": []}], "valid": true, "plugin": "TranslationManager", "preset": "standard", "domain_id": 1, "default_language": "en"}}	\N	2026-08-21 23:36:58.441086
4	13	SystemManager	1	setup	success	standard	{"resolved": {"pages": [{"title": "System", "issues": [], "titles": {"en": "System", "uk": "Система"}, "resolved": {"slug": "admin-system", "layout": "admin", "recipe_id": 1, "recipe_name": "admin", "system_name": "system"}, "requested": {"slug": "system", "recipe_name": "admin", "system_name": "system"}, "navigation": [{"menu_id": 5, "resolved": "sidebar_nav", "requested": "sidebar_nav"}], "resource_key": "page:0", "recipe_snapshot": {"name": "Admin page", "payload": {"layout": "admin", "wrappers": [], "page_prefix": "admin-", "default_navigation_menus": ["sidebar_nav"]}, "recipe_id": 1, "created_at": "2026-08-21 23:17:46.153019", "recipe_key": "admin", "updated_at": "2026-08-21 23:32:46", "description": null, "recipe_uuid": "f31b3114-f367-402c-8cf7-f45cddbe58ba"}, "preset_instances": [{"skip": false, "plugin": "SystemManager", "wrapper": "content_middle", "requested": {"action": "overview", "handler": "manage", "instance_params": []}, "instance_params": {"action": "overview", "handler": "manage"}}], "recipe_instances": []}], "valid": true, "plugin": "SystemManager", "preset": "standard", "domain_id": 1, "default_language": "en"}, "requested": {"pages": [{"title": "System", "issues": [], "titles": {"en": "System", "uk": "Система"}, "resolved": {"slug": "admin-system", "layout": "admin", "recipe_id": 1, "recipe_name": "admin", "system_name": "system"}, "requested": {"slug": "system", "recipe_name": "admin", "system_name": "system"}, "navigation": [{"menu_id": 5, "resolved": "sidebar_nav", "requested": "sidebar_nav"}], "resource_key": "page:0", "recipe_snapshot": {"name": "Admin page", "payload": {"layout": "admin", "wrappers": [], "page_prefix": "admin-", "default_navigation_menus": ["sidebar_nav"]}, "recipe_id": 1, "created_at": "2026-08-21 23:17:46.153019", "recipe_key": "admin", "updated_at": "2026-08-21 23:32:46", "description": null, "recipe_uuid": "f31b3114-f367-402c-8cf7-f45cddbe58ba"}, "preset_instances": [{"skip": false, "plugin": "SystemManager", "wrapper": "content_middle", "requested": {"action": "overview", "handler": "manage", "instance_params": []}, "instance_params": {"action": "overview", "handler": "manage"}}], "recipe_instances": []}], "valid": true, "plugin": "SystemManager", "preset": "standard", "domain_id": 1, "default_language": "en"}}	\N	2026-08-22 10:13:22.169321
5	14	UserManager	1	setup	success	standard	{"resolved": {"pages": [{"title": "Users and access", "issues": [], "titles": {"en": "Users and access", "uk": "Користувачі та доступ"}, "resolved": {"slug": "admin-users", "layout": "admin", "recipe_id": 1, "recipe_name": "admin", "system_name": "users"}, "requested": {"slug": "users", "recipe_name": "admin", "system_name": "users"}, "navigation": [{"menu_id": 5, "resolved": "sidebar_nav", "requested": "sidebar_nav"}], "resource_key": "page:0", "recipe_snapshot": {"name": "Admin page", "payload": {"layout": "admin", "wrappers": [], "page_prefix": "admin-", "default_navigation_menus": ["sidebar_nav"]}, "recipe_id": 1, "created_at": "2026-08-21 23:17:46.153019", "recipe_key": "admin", "updated_at": "2026-08-21 23:32:46", "description": null, "recipe_uuid": "f31b3114-f367-402c-8cf7-f45cddbe58ba"}, "preset_instances": [{"skip": false, "plugin": "UserManager", "wrapper": "content_middle", "requested": {"action": "overview", "handler": "manage", "instance_params": []}, "instance_params": {"action": "overview", "handler": "manage"}}], "recipe_instances": []}], "valid": true, "plugin": "UserManager", "preset": "standard", "domain_id": 1, "default_language": "en"}, "requested": {"pages": [{"title": "Users and access", "issues": [], "titles": {"en": "Users and access", "uk": "Користувачі та доступ"}, "resolved": {"slug": "admin-users", "layout": "admin", "recipe_id": 1, "recipe_name": "admin", "system_name": "users"}, "requested": {"slug": "users", "recipe_name": "admin", "system_name": "users"}, "navigation": [{"menu_id": 5, "resolved": "sidebar_nav", "requested": "sidebar_nav"}], "resource_key": "page:0", "recipe_snapshot": {"name": "Admin page", "payload": {"layout": "admin", "wrappers": [], "page_prefix": "admin-", "default_navigation_menus": ["sidebar_nav"]}, "recipe_id": 1, "created_at": "2026-08-21 23:17:46.153019", "recipe_key": "admin", "updated_at": "2026-08-21 23:32:46", "description": null, "recipe_uuid": "f31b3114-f367-402c-8cf7-f45cddbe58ba"}, "preset_instances": [{"skip": false, "plugin": "UserManager", "wrapper": "content_middle", "requested": {"action": "overview", "handler": "manage", "instance_params": []}, "instance_params": {"action": "overview", "handler": "manage"}}], "recipe_instances": []}], "valid": true, "plugin": "UserManager", "preset": "standard", "domain_id": 1, "default_language": "en"}}	\N	2026-08-22 11:58:08.073394
6	15	Media	\N	install	success	\N	{"reference": "Media"}	\N	2026-08-23 04:42:21.359922
7	15	Media	\N	update	success	\N	{"reference": "Media"}	\N	2026-08-24 16:37:30.758958
8	8	Forms	\N	update	success	\N	{"reference": "Forms"}	\N	2026-08-24 16:50:13.507814
9	17	Mailer	\N	install	success	\N	{"reference": "Mailer"}	\N	2026-08-25 13:09:12.867354
10	18	Notifications	\N	install	success	\N	{"reference": "Notifications"}	\N	2026-08-26 16:30:53.275058
11	\N	ContentViewer	\N	uninstall	success	\N	{"reference": "ContentViewer"}	\N	2026-08-28 14:02:35.713641
12	\N	SportQuizCore	\N	uninstall	success	\N	{"reference": "SportQuizCore"}	\N	2026-08-28 14:03:07.761846
13	\N	UserLogin	\N	uninstall	success	\N	{"reference": "UserLogin"}	\N	2026-08-28 14:03:42.98401
14	\N	UserAuth	\N	uninstall	success	\N	{"reference": "UserAuth"}	\N	2026-08-28 14:12:10.712342
15	\N	ViewArticles	\N	update	success	\N	{"reference": "ViewArticles"}	\N	2026-08-31 14:46:58.729959
16	\N	ViewArticles	\N	uninstall	success	\N	{"reference": "ViewArticles"}	\N	2026-08-31 14:49:37.809426
17	19	ViewArticles	\N	install	success	\N	{"reference": "ViewArticles"}	\N	2026-08-31 14:49:43.425065
18	19	ViewArticles	\N	update	success	\N	{"reference": "ViewArticles"}	\N	2026-08-31 15:07:27.965355
19	19	ViewArticles	\N	update	success	\N	{"reference": "ViewArticles"}	\N	2026-08-31 15:55:42.448919
20	19	ViewArticles	\N	update	success	\N	{"reference": "ViewArticles"}	\N	2026-08-31 20:01:01.430755
21	20	Formatter	\N	install	success	\N	{"reference": "Formatter"}	\N	2026-09-01 09:40:35.384874
22	\N	ContentSearch	\N	uninstall	success	\N	{"reference": "ContentSearch"}	\N	2026-09-03 14:53:16.717943
\.


--
-- Data for Name: pm_setup_resources; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.pm_setup_resources (setup_resource_id, setup_id, resource_key, resource_type, resource_id, resource_uuid, ownership, recipe_id, recipe_snapshot, config, created_at, updated_at) FROM stdin;
2	3	page:0:preset:instance:0	page_plugin_instance	9	6c74d3b1-7bf2-4186-a592-c41663bf7fdd	created	1	{"name": "Admin page", "payload": {"layout": "admin", "wrappers": [], "page_prefix": "admin-", "default_navigation_menus": ["sidebar_nav"]}, "recipe_id": 1, "created_at": "2026-08-21 23:17:46.153019", "recipe_key": "admin", "updated_at": "2026-08-21 23:32:46", "description": null, "recipe_uuid": "f31b3114-f367-402c-8cf7-f45cddbe58ba"}	{"source": "preset", "resolved": {"plugin": "TranslationManager", "page_id": 9, "wrapper": "content_middle", "instance_index": 0, "instance_params": {"action": "default", "handler": "manage"}}, "requested": {"action": "default", "handler": "manage", "instance_params": []}}	2026-08-21 23:36:58.441086	2026-08-21 23:36:58
3	3	page:0:navigation:0	navigation_item	20	f1d7852c-bdea-4800-ad5e-d136b0b5a712	created	1	{"name": "Admin page", "payload": {"layout": "admin", "wrappers": [], "page_prefix": "admin-", "default_navigation_menus": ["sidebar_nav"]}, "recipe_id": 1, "created_at": "2026-08-21 23:17:46.153019", "recipe_key": "admin", "updated_at": "2026-08-21 23:32:46", "description": null, "recipe_uuid": "f31b3114-f367-402c-8cf7-f45cddbe58ba"}	{"source": "recipe", "resolved": {"item_id": 20, "menu_id": 5, "menu_key": "sidebar_nav"}, "requested": {"menu_key": "sidebar_nav"}}	2026-08-21 23:36:58.441086	2026-08-21 23:36:58
4	4	page:0	page	10	11abbb1e-46b8-48c9-8bf0-7a401359ee07	created	1	{"name": "Admin page", "payload": {"layout": "admin", "wrappers": [], "page_prefix": "admin-", "default_navigation_menus": ["sidebar_nav"]}, "recipe_id": 1, "created_at": "2026-08-21 23:17:46.153019", "recipe_key": "admin", "updated_at": "2026-08-21 23:32:46", "description": null, "recipe_uuid": "f31b3114-f367-402c-8cf7-f45cddbe58ba"}	{"source": "preset", "resolved": {"slug": "admin-system", "layout": "admin", "recipe_id": 1, "recipe_name": "admin", "system_name": "system"}, "requested": {"slug": "system", "recipe_name": "admin", "system_name": "system"}}	2026-08-22 10:13:22.169321	2026-08-22 10:13:22
5	4	page:0:preset:instance:0	page_plugin_instance	10	11abbb1e-46b8-48c9-8bf0-7a401359ee07	created	1	{"name": "Admin page", "payload": {"layout": "admin", "wrappers": [], "page_prefix": "admin-", "default_navigation_menus": ["sidebar_nav"]}, "recipe_id": 1, "created_at": "2026-08-21 23:17:46.153019", "recipe_key": "admin", "updated_at": "2026-08-21 23:32:46", "description": null, "recipe_uuid": "f31b3114-f367-402c-8cf7-f45cddbe58ba"}	{"source": "preset", "resolved": {"plugin": "SystemManager", "page_id": 10, "wrapper": "content_middle", "instance_index": 0, "instance_params": {"action": "overview", "handler": "manage"}}, "requested": {"action": "overview", "handler": "manage", "instance_params": []}}	2026-08-22 10:13:22.169321	2026-08-22 10:13:22
6	4	page:0:navigation:0	navigation_item	21	4e19dfae-e1de-4bbe-bf49-cbacd3f52f12	created	1	{"name": "Admin page", "payload": {"layout": "admin", "wrappers": [], "page_prefix": "admin-", "default_navigation_menus": ["sidebar_nav"]}, "recipe_id": 1, "created_at": "2026-08-21 23:17:46.153019", "recipe_key": "admin", "updated_at": "2026-08-21 23:32:46", "description": null, "recipe_uuid": "f31b3114-f367-402c-8cf7-f45cddbe58ba"}	{"source": "recipe", "resolved": {"item_id": 21, "menu_id": 5, "menu_key": "sidebar_nav"}, "requested": {"menu_key": "sidebar_nav"}}	2026-08-22 10:13:22.169321	2026-08-22 10:13:22
7	5	page:0	page	11	c6b29462-4207-45db-be29-9579d9521d5f	created	1	{"name": "Admin page", "payload": {"layout": "admin", "wrappers": [], "page_prefix": "admin-", "default_navigation_menus": ["sidebar_nav"]}, "recipe_id": 1, "created_at": "2026-08-21 23:17:46.153019", "recipe_key": "admin", "updated_at": "2026-08-21 23:32:46", "description": null, "recipe_uuid": "f31b3114-f367-402c-8cf7-f45cddbe58ba"}	{"source": "preset", "resolved": {"slug": "admin-users", "layout": "admin", "recipe_id": 1, "recipe_name": "admin", "system_name": "users"}, "requested": {"slug": "users", "recipe_name": "admin", "system_name": "users"}}	2026-08-22 11:58:08.073394	2026-08-22 11:58:08
8	5	page:0:preset:instance:0	page_plugin_instance	11	c6b29462-4207-45db-be29-9579d9521d5f	created	1	{"name": "Admin page", "payload": {"layout": "admin", "wrappers": [], "page_prefix": "admin-", "default_navigation_menus": ["sidebar_nav"]}, "recipe_id": 1, "created_at": "2026-08-21 23:17:46.153019", "recipe_key": "admin", "updated_at": "2026-08-21 23:32:46", "description": null, "recipe_uuid": "f31b3114-f367-402c-8cf7-f45cddbe58ba"}	{"source": "preset", "resolved": {"plugin": "UserManager", "page_id": 11, "wrapper": "content_middle", "instance_index": 0, "instance_params": {"action": "overview", "handler": "manage"}}, "requested": {"action": "overview", "handler": "manage", "instance_params": []}}	2026-08-22 11:58:08.073394	2026-08-22 11:58:08
9	5	page:0:navigation:0	navigation_item	22	7d394773-29a3-42b5-8970-8fbee6284d80	created	1	{"name": "Admin page", "payload": {"layout": "admin", "wrappers": [], "page_prefix": "admin-", "default_navigation_menus": ["sidebar_nav"]}, "recipe_id": 1, "created_at": "2026-08-21 23:17:46.153019", "recipe_key": "admin", "updated_at": "2026-08-21 23:32:46", "description": null, "recipe_uuid": "f31b3114-f367-402c-8cf7-f45cddbe58ba"}	{"source": "recipe", "resolved": {"item_id": 22, "menu_id": 5, "menu_key": "sidebar_nav"}, "requested": {"menu_key": "sidebar_nav"}}	2026-08-22 11:58:08.073394	2026-08-22 11:58:08
1	3	page:0	page	9	6c74d3b1-7bf2-4186-a592-c41663bf7fdd	created	1	{"name": "Admin page", "payload": {"layout": "admin", "wrappers": [], "page_prefix": "admin-", "default_navigation_menus": ["sidebar_nav"]}, "recipe_id": 1, "created_at": "2026-08-21 23:17:46.153019", "recipe_key": "admin", "updated_at": "2026-08-21 23:32:46", "description": null, "recipe_uuid": "f31b3114-f367-402c-8cf7-f45cddbe58ba"}	{"source": "preset", "resolved": {"slug": "admin-translations", "layout": "admin", "recipe_id": 1, "recipe_name": "admin", "system_name": "translations"}, "requested": {"slug": "translations", "recipe_name": "admin", "system_name": "translations"}}	2026-08-21 23:36:58.441086	2026-08-21 23:36:58
\.


--
-- Data for Name: secrets; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.secrets (secret_id, namespace, secret_name, domain_id, encrypted_value, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.sessions (domain_id, session_id, user_id, created_at, updated_at, is_persistent, ua_hash, data) FROM stdin;
\.


--
-- Data for Name: theme_layouts; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.theme_layouts (layout_id, uuid, theme_id, system_name, layout_filename, wrappers) FROM stdin;
1	ae04e161-6ec8-48e1-a07d-fe892fad9810	1	main	layouts/main	{"top_plugins": [], "head_plugins": [], "hero_plugins": [], "footer_plugins": [], "content_plugins": []}
2	befa6b10-2cbd-42a5-a7b0-bb011d8f7a6b	1	simple	layouts/simple	{"top_plugins": [], "head_plugins": [], "footer_plugins": [], "content_plugins": []}
3	e4689b90-20de-4f33-a71a-4cbfc9d05a27	1	admin	layouts/admin	{"sidebar": [], "content_top": [], "head_plugins": [], "content_middle": [], "content_bottoms": []}
4	618ab4f2-af51-42a3-856e-876cf1046ada	1	sidebar	layouts/page_with_sidebar	{"top_plugins": [], "head_plugins": [], "footer_plugins": [], "content_plugins": [], "sidebar_plugins": []}
\.


--
-- Data for Name: themes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.themes (theme_id, uuid, system_name, created_at, theme_settings, theme_version) FROM stdin;
1	d5c204bc-bd72-40ec-87d5-d2571301eebc	default	2025-10-12 06:36:56.55334	[]	0.1.1
\.


--
-- Data for Name: tokens; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.tokens (token_id, user_id, method, token, expires_at, token_data) FROM stdin;
\.


--
-- Data for Name: translations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.translations (translation_id, entity_uuid, lang_code, translated_data, translation_status, is_default, updated_at) FROM stdin;
24	00000000-0000-0000-0000-000000000000	en	{"lang_save": "Save", "lang_submit": "Submit", "lang_access_denied": "Access denied", "lang_select_action": "Plugin action", "lang_page_not_found": "Page not found", "lang_return_to_home": "Return to <a href=\\"/\\">homepage</a>."}	draft	f	2026-09-06 01:53:33.816741+00
348	00000000-0000-0000-0000-000000000000	uk	{"lang_save": "Зберегти", "lang_submit": "Відправити", "lang_access_denied": "Доступ заборонено", "lang_select_action": "Оберіть дію", "lang_page_not_found": "Сторінку не знайдено", "lang_return_to_home": "Повернутися на <a href=\\"/\\">головну</a>."}	draft	f	2026-09-06 01:53:33.816425+00
358	6b53edee-0889-409a-b195-e3dd0ca56f91	uk	{"tags": ["Articles", "Kami"], "summary": "Стаття - це лише один приклад структури контенту. Ви можете змінювати, видаляти чи додавати поля в залежності від потреб конкретного проєкту.", "article_body": "<p>Кожне поле може мати своє призначення та особливості поведінки: деякі значення можуть перекладатися, тоді як інші залишаються незмінними в усіх мовах. Шаблон визначає, як саме цей вміст відображається на сторінці.</p><p>Головне - це розділення вмісту, структури та оформлення. Ви можете змінювати одне з них, не переробляючи все інше навколо нього.</p>", "display_title": "Це просто стаття"}	draft	f	2026-09-06 02:02:59.792935+00
359	019dc822-41f5-4d3c-b01b-7c2e72a32df7	uk	{"tags": ["interior"], "summary": "Кімната може виглядати зовсім інакше, навіть якщо її розміри не змінилися. Іноді найбільшу різницю створюють прості речі: те, куди падає світло, як розташовані предмети та скільки вільного простору залишається між ними.", "article_body": "<p><strong>Природне світло</strong> змушує поверхні, текстури та кольори змінюватися протягом дня. Форма, яка вранці здається плоскою, до вечора може стати найвиразнішим елементом у кімнаті.</p><p><strong>Порожній простір</strong> має таке ж значення, як і предмети, розміщені в ньому. Якщо дати речам простір для «дихання», навіть невелика кімната може здаватися спокійнішою та більш продуманою.</p><p>Гарні інтер’єри рідко залежать від якоїсь однієї ефектної деталі. Найчастіше їхня краса полягає у балансі світла, форм, текстур та спокійних зон між ними.</p>", "display_title": "Світло, форма та простір"}	draft	f	2026-09-06 02:07:01.845446+00
360	2f561283-698c-4989-b5ef-4a7cfdcea34d	uk	{"static_content_body": "<h3>Незалежний контент</h3><p>Кожна картка в цій таблиці є окремим блоком контенту. Її можна редагувати та перекладати незалежно від інших.</p>"}	draft	f	2026-09-04 10:41:58.763239+00
361	66859df9-5e84-431e-9929-b7a19b9f2570	uk	{"static_content_body": "<h3>Одна група</h3><p>Група просто об’єднує кілька блоків. У цьому випадку її шаблон відображає їх у вигляді адаптивної сітки.</p>"}	draft	f	2026-09-04 10:42:56.825956+00
281	494d53ed-18e7-44e3-b3b1-79eecfd5ee34	uk	{"item_title": "Статична сторінка"}	draft	f	2026-09-06 02:22:54.538368+00
319	016b78c1-25cb-4ca6-8c55-80f0bd62db49	uk	{"title": "Доступ до API", "phrases": {"copy": "Копіювати", "done": "Готово", "edit": "Редагувати", "name": "Назва", "save": "Зберегти", "view": "Перегляд", "never": "Без обмеження", "title": "Доступ до API", "token": "Токен", "access": "Доступ", "active": "Активний", "cancel": "Скасувати", "copied": "Скопійовано", "create": "Створити токен", "delete": "Видалити", "enable": "Увімкнути", "revoke": "Відкликати", "status": "Статус", "actions": "Дії", "created": "Створено", "disable": "Вимкнути", "expired": "Прострочений", "expires": "Діє до", "revoked": "Відкликаний", "disabled": "Вимкнений", "last_used": "Використано востаннє", "no_tokens": "API-токенів поки немає.", "created_at": "Створено", "expires_at": "Діє до", "never_used": "Ще не використовувався", "token_hint": "Збережений токен", "api_actions": "Методи API", "description": "Створення та керування персональними токенами для зовнішніх застосунків.", "permissions": "Дозволи", "types_count": "Типів контенту: {count}", "expires_help": "Залиште порожнім, якщо строк дії токена не обмежений.", "invalid_name": "Назва токена обов'язкова.", "access_denied": "Для вашої групи користувачів доступ до API не дозволений.", "actions_count": "Методів: {count}", "invalid_token": "API-токен не знайдено.", "token_created": "Токен створено", "confirm_delete": "Видалити запис цього відкликаного або простроченого токена?", "confirm_revoke": "Відкликати цей токен назавжди? Відновити його буде неможливо, а застосункам знадобиться новий токен.", "content_access": "Доступ до контенту", "invalid_expiry": "Дата закінчення дії має бути в майбутньому.", "no_api_actions": "Наразі немає доступних методів API.", "confirm_disable": "Тимчасово вимкнути цей токен? Запити з ним відхилятимуться, доки ви не ввімкнете його знову.", "deleted_success": "Запис токена видалено.", "edit_permission": "Редагування", "enabled_success": "Токен увімкнено.", "revoked_success": "Токен відкликано назавжди.", "token_hint_help": "Для розпізнавання зберігається лише цей короткий фрагмент. Повний токен відновити неможливо.", "token_name_help": "Вкажіть зрозумілу назву, наприклад Мобільний застосунок або CRM sync.", "updated_success": "Токен успішно оновлено.", "api_actions_help": "Оберіть методи API, які зможе викликати цей токен. Показано лише доступні вашому обліковому запису.", "disabled_success": "Токен тимчасово вимкнено.", "create_permission": "Створення", "delete_permission": "Видалення", "token_created_help": "Скопіюйте цей токен зараз. Повторно він більше не відображатиметься.", "content_access_help": "Обмежте токен підмножиною ваших поточних дозволів на контент.", "no_content_permissions": "Наразі немає доступних дозволів на контент."}, "handlers": {"manage": {"title": "Керування доступом до API", "actions": {"tokenEdit": {"title": "Редагувати API-токен"}, "tokenList": {"title": "Список API-токенів"}, "tokenSave": {"title": "Зберегти API-токен"}, "tokenDelete": {"title": "Видалити API-токен"}, "tokenEnable": {"title": "Увімкнути API-токен"}, "tokenRevoke": {"title": "Відкликати API-токен"}, "tokenDisable": {"title": "Тимчасово вимкнути API-токен"}}}}, "description": "Керування персональними токенами доступу до API."}	draft	f	2026-09-02 14:03:19.029886+00
1	3564f84d-fdac-4250-acb0-b91bcedd5c1c	en	{"title": "Navigation", "phrases": {"url": "URL", "edit": "Edit", "save": "Save", "menus": "Menus", "pages": "Pages", "title": "Title", "users": "Users", "cancel": "Cancel", "delete": "Delete", "logout": "Log out", "actions": "Actions", "content": "Content", "add_item": "Add item", "menu_key": "Menu key", "no_menus": "No menus found.", "settings": "Settings", "drag_item": "Drag menu item", "menu_name": "Menu name", "menu_count": "{count} menus", "menu_items": "Menu items", "navigation": "Navigation", "add_subitem": "Add subitem", "create_menu": "Create menu", "delete_item": "Delete item", "manage_menus": "Create and edit navigation menus.", "menu_deleted": "Menu deleted.", "translations": "Translations", "back_to_menus": "Back to menus", "delete_failed": "Failed to delete the menu.", "icon_optional": "Icon (optional)", "menu_settings": "Menu settings", "edit_menu_help": "Edit menu settings and arrange its items.", "menu_not_found": "Menu not found.", "menu_items_help": "Drag items to reorder them or move them between menu levels.", "menu_description": "Menu description", "menu_key_warning": "Changing this key may break recipes, plugin settings, or theme bindings. Change it only if you understand the consequences.", "menu_key_required": "Menu key is required.", "menu_name_required": "Menu name is required.", "confirm_delete_item": "Are you sure you want to delete this item? This action cannot be undone.", "confirm_delete_menu": "Delete “{title}”? This action cannot be undone."}, "handlers": {"view": {"title": "Display navigation", "actions": {"show": {"title": "Show menu"}}, "instance_params": {"menu_id": {"title": "Menu to display", "description": "Select a menu to display."}, "template": {"title": "Navigation template", "options": {"footer": "Footer menu", "topnav": "Top navigation panel", "sidebar": "Sidebar menu"}, "description": "Select the template used to render the navigation menu."}}}, "manage": {"title": "Manage navigation", "actions": {"edit": {"title": "Edit menu"}, "list": {"title": "List menus"}, "save": {"title": "Save menu"}, "create": {"title": "Create menu"}, "delete": {"title": "Delete menu"}}}}, "description": "Create navigation menus and render them in page layouts."}	draft	f	2026-08-28 16:29:02.013422+00
2	c19033b7-c7b4-47c3-a3c2-56dc10d2b36f	en	{"title":"Theme manager","description":"Install, update and remove themes.","handlers":{"manage":{"title":"Manage","actions":{"overview":{"title":"Themes"},"lifecycle":{"title":"Install, update or uninstall theme"}}}},"phrases":{"themes":"Themes","themes_help":"Themes found in the themes directory and installed theme records.","theme":"Theme","version":"Version","status":"Status","used_on":"Used on","actions":"Actions","install":"Install","update":"Update","uninstall":"Uninstall","installed":"Installed","not_installed":"Not installed","files_missing":"Files missing","confirm_uninstall":"Uninstall this theme?","operation_failed":"Theme operation failed."}}	draft	f	2026-08-28 16:29:02.013422+00
3	d5c204bc-bd72-40ec-87d5-d2571301eebc	en	{"title": "Default theme", "description": "Basic theme for Kami"}	draft	f	2026-08-28 16:29:02.013422+00
4	b5db77b6-0a14-4c02-9d23-e697a1599680	en	{"info": {"plugin_title": "User bar", "plugin_description": "Usually displayed in top right corner"}, "settings": {"config": {"menu_id": {"title": "User context menu", "description": "Select custom context menu created in Navication Menu plugin."}, "login_action": {"title": "Action after authorization", "options": [{"title": "Reload page"}, {"title": "Do nothing"}, {"title": "Redirect to page"}], "description": "Що відбувається після авторизації."}, "registration": {"title": "Registration enabled", "description": "The user cannot log in to the domain if this option is disabled."}}, "handlers": {"view": {"title": "View"}}}, "dictionary": {"lang_email": "Email", "lang_guest": "Guest", "lang_login": "Sign in", "lang_logout": "Log out", "lang_welcome": "Welcome,", "lang_password": "Password", "lang_register": "Register", "lang_username": "Username", "lang_remember_me": "Remember me", "lang_repeat_password": "Repeat password", "lang_username_or_email": "Email or username"}}	draft	f	2026-08-28 16:29:02.013422+00
5	10bc994f-613b-403f-8325-5c45d936d85b	en	{"title": "Number", "description": "Base root type for numeric values."}	draft	f	2026-08-28 16:29:02.013422+00
6	773bd1a9-af28-401f-98ae-bcf67fbfe00d	en	{"title": "Date", "description": "Base root type for calendar and time-related values."}	draft	f	2026-08-28 16:29:02.013422+00
7	b485a84e-abb0-4f35-afbb-bff0877f2611	en	{"title": "Text", "description": "Base root type for textual values."}	draft	f	2026-08-28 16:29:02.013422+00
8	97c33e18-0fe1-4f1b-af2d-8c23180efe3e	en	{"title": "Boolean", "description": "Base type for true or false values."}	draft	f	2026-08-28 16:29:02.013422+00
9	6cfd22d3-8c09-43fa-84f9-5db73a5df602	en	{"variant_title": "string", "variant_description": null}	draft	f	2026-08-28 16:29:02.013422+00
10	3bba0cff-de34-4db0-aebf-1e6945ec73ea	en	{"variant_title": "plain text", "variant_description": null}	draft	f	2026-08-28 16:29:02.013422+00
11	bcad116f-8765-402b-97d9-453e99e82dec	en	{"variant_title": "html", "variant_description": null}	draft	f	2026-08-28 16:29:02.013422+00
12	80ac7cc8-d436-415a-9786-4b301ef7699a	en	{"variant_title": "url", "variant_description": null}	draft	f	2026-08-28 16:29:02.013422+00
13	ea3d7e4b-1009-4d58-a8ce-83f0d5de206c	en	{"variant_title": "integer", "variant_description": null}	draft	f	2026-08-28 16:29:02.013422+00
14	c9c9a97b-ea1f-4e24-a315-3057a4f4df76	en	{"variant_title": "float", "variant_description": null}	draft	f	2026-08-28 16:29:02.013422+00
15	7c9918a4-0773-4adb-a2c5-03c207440f2a	en	{"variant_title": "currency", "variant_description": null}	draft	f	2026-08-28 16:29:02.013422+00
16	6d415be6-5f83-4cc6-8e2c-601c8c647101	en	{"variant_title": "usergroup", "variant_description": null}	draft	f	2026-08-28 16:29:02.013422+00
17	bffe2257-a829-4727-a87b-ffee774930ec	en	{"variant_title": "user", "variant_description": null}	draft	f	2026-08-28 16:29:02.013422+00
18	bfef030f-6ac5-4423-b1cb-1aa2fa06f40f	en	{"variant_title": "page", "variant_description": null}	draft	f	2026-08-28 16:29:02.013422+00
19	fce0e79a-7416-47cb-b869-c8742e373b43	en	{"variant_title": "domain", "variant_description": null}	draft	f	2026-08-28 16:29:02.013422+00
20	6920f864-3b6d-417a-b52e-c8d7d20abce5	en	{"variant_title": "content type", "variant_description": null}	draft	f	2026-08-28 16:29:02.013422+00
21	bc50c8dd-e512-4fc1-99eb-19fa999b0e79	en	{"variant_title": "field", "variant_description": null}	draft	f	2026-08-28 16:29:02.013422+00
22	d072fe9d-7ca0-45e7-8fa0-77209721783f	en	{"variant_title": "checkbox", "variant_description": null}	draft	f	2026-08-28 16:29:02.013422+00
23	972f0b59-94d6-4abb-953d-3f8de98f2588	en	{"variant_title": "content item", "variant_description": null}	draft	f	2026-08-28 16:29:02.013422+00
25	75c059a3-d9e8-4572-8d18-5f4823266a99	en	{"title": "Menu", "description": "Navigation menu."}	draft	f	2026-08-28 16:29:02.013422+00
26	4ff2a6c5-6b64-4344-a33a-0f14e8b9bc55	en	{"title": "Menu item", "description": "Navigation menu item."}	draft	f	2026-08-28 16:29:02.013422+00
27	d321d868-75cc-4e25-b071-17551730237f	en	{"title": "Item name", "description": "Цей напис відображатиметься у меню"}	draft	f	2026-08-28 16:29:02.013422+00
28	8046ae68-c71b-4653-ae07-835fadce0998	en	{"title": "URL", "description": "Посилання для цього пункту меню"}	draft	f	2026-08-28 16:29:02.013422+00
29	c0e9cb3c-e4ac-40b4-a409-f0eae41a2123	en	{"title": "Icon", "description": "Іконка (відображатиметься у відповідних темплейтах)"}	draft	f	2026-08-28 16:29:02.013422+00
30	1d13122d-5481-44ff-b340-ab9473a3665e	en	[]	draft	f	2026-08-28 16:29:02.013422+00
31	57b66e95-9488-408a-8a3f-85b6ca891098	en	{"item_title": "Home333"}	draft	f	2026-08-28 16:29:02.013422+00
32	494d53ed-18e7-44e3-b3b1-79eecfd5ee34	en	{"item_title": "Static page"}	draft	f	2026-09-03 14:59:47.664842+00
33	7a8a658a-d9f1-412f-9aeb-a8f203062f27	en	{"item_title": "Blog"}	draft	f	2026-09-03 14:59:47.665548+00
34	18bfd168-a278-42f9-8468-92f0dec931bf	en	{"title": "Page Manager", "phrases": {"edit": "Edit", "head": "Head", "name": "Name", "save": "Save", "slug": "Slug", "pages": "Pages", "retry": "Retry", "title": "Title", "cancel": "Cancel", "delete": "Delete", "domain": "Domain", "footer": "Footer", "layout": "Layout", "recipe": "Recipe", "actions": "Actions", "content": "Content", "recipes": "Recipes", "add_page": "Add page", "no_pages": "No pages found.", "edit_page": "Edit page", "save_page": "Save page", "no_recipes": "No recipes found.", "page_saved": "Page saved.", "recipe_key": "Recipe key", "create_page": "Create page", "delete_page": "Delete page", "description": "Description", "edit_recipe": "Edit recipe", "move_plugin": "Move plugin", "parent_page": "Parent page", "top_section": "Top section", "new_page_for": "New page for", "page_recipes": "Page recipes", "payload_json": "Payload JSON", "back_to_pages": "Back to pages", "layout_loaded": "Layout loaded", "loading_pages": "Loading pages…", "remove_plugin": "Remove plugin", "select_domain": "Select domain…", "select_recipe": "Select recipe…", "layout_preview": "Page layout", "loading_layout": "Loading page layout…", "manage_domains": "Manage domains", "no_parent_page": "No parent page", "page_count_few": "{count} pages", "page_count_one": "{count} page", "select_handler": "Select handler", "page_count_many": "{count} pages", "plugin_settings": "Plugin settings", "unknown_wrapper": "This zone is not declared by the layout.", "page_count_other": "{count} pages", "available_plugins": "Available plugins", "drag_plugins_help": "Drag a plugin into any available page zone.", "drag_plugins_here": "Drag plugins here", "load_pages_failed": "Failed to load pages.", "parent_page_cycle": "A page cannot be placed under itself or one of its child pages.", "unplaced_wrappers": "Unplaced wrappers", "create_from_recipe": "From recipe", "delete_page_failed": "Failed to delete the page.", "domain_unavailable": "Domain is not available.", "layout_load_failed": "Failed to load the page layout.", "no_domain_selected": "No domain selected", "recipe_page_prefix": "URL prefix: {prefix}", "delete_page_confirm": "Delete “{title}”? This action cannot be undone.", "invalid_parent_page": "The selected parent page is invalid.", "layout_preview_help": "The zones below follow the structure of the selected layout.", "delete_recipe_confirm": "Delete this recipe?", "recipe_no_page_prefix": "No URL prefix", "create_from_recipe_for": "New page from recipe for", "unplaced_wrappers_help": "These wrappers contain data but have no matching zone in the layout preview.", "loading_plugin_settings": "Loading plugin settings…", "page_recipes_description": "Reusable page blueprints for layouts, standard plugin instances, and default navigation.", "plugin_settings_load_failed": "Failed to load plugin settings. Please try again.", "select_domain_to_view_pages": "Select a domain to view its pages"}, "handlers": {"manage": {"title": "Manage pages", "actions": {"edit": {"title": "Edit page"}, "list": {"title": "List pages"}, "save": {"title": "Save page"}, "config": {"title": "Page settings"}, "delete": {"title": "Delete page"}, "recipes": {"title": "Page recipes"}, "settings": {"title": "Page Manager settings"}, "createPage": {"title": "Create page"}, "deletePage": {"title": "Delete page"}, "recipeSave": {"title": "Save page recipe"}, "domainPages": {"title": "Load domain pages"}, "recipeDelete": {"title": "Delete page recipe"}, "pageLayoutData": {"title": "Load page layout"}, "pluginContextForm": {"title": "Plugin instance settings"}, "createPageFromRecipe": {"title": "Create page from recipe"}}}}, "settings": {"test": {"title": "Test setting", "description": "Temporary setting used during development."}}, "description": "Create pages, select layouts, and place plugins into page sections."}	draft	f	2026-08-28 16:29:02.013422+00
35	a871c1f4-5040-47b8-9f37-19f0e0988020	en	{"title":"Content Manager","phrases":{"name":"Name","save":"Save","field":"Field","items":"Items","order":"Order","title":"Title","cancel":"Cancel","delete":"Delete","fields":"Fields","hidden":"Hidden","search":"Search…","source":"Source","status":"Status","unique":"Unique","actions":"Actions","content":"Content","indexed":"Indexed","move_up":"Move up","used_in":"Used in","has_slug":"Items have slugs","inactive":"inactive","multiple":"Multiple values","new_item":"New item","no_items":"No content items found.","no_owner":"No owner","readonly":"Read only","required":"Required","edit_item":"Edit item","move_down":"Move down","no_fields":"No fields found.","root_only":"Root access required.","edit_field":"Edit field","field_type":"Field type","item_count":"{count} items","item_saved":"Item saved.","no_manager":"No manager","open_items":"Open items","overridden":"Overridden","save_field":"Save field","type_count":"{count} content types","delete_item":"Delete content item","description":"Description","field_count":"{count} fields","field_saved":"Field saved.","fields_help":"Add existing fields, create new ones and set their order.","system_name":"System name","title_field":"Title field","types_count":"Content types","attach_field":"Add existing field","content_type":"Content type","create_field":"Create field","detach_field":"Remove from structure","field_unused":"Unused","invalid_item":"Invalid content item.","item_deleted":"Item deleted.","manage_items":"Search, create, edit and delete content items.","owner_plugin":"Owner plugin","save_manager":"Save manager","translatable":"Translatable","back_to_items":"Back to items","content_types":"Content types","default_value":"Default value","field_deleted":"Field deleted.","from_manifest":"From manifest","manage_fields":"Fields","manager_reset":"Manifest manager restored.","manager_saved":"Content type manager saved.","search_fields":"Search fields…","search_weight":"Search weight","stored_values":"Has values","summary_field":"Summary field","back_to_fields":"Back to fields","edit_item_help":"Edit the content fields and save your changes.","edit_structure":"Edit structure","existing_field":"Existing field","field_attached":"Field added to the structure.","field_detached":"Field removed from the structure and its values for this content type were deleted.","field_settings":"Field settings","item_not_found":"Content item not found.","manage_schemas":"Manage schemas and content items.","manual_default":"Manual default","no_parent_type":"No parent type","saving_manager":"Saving…","type_has_items":"Delete all content items first.","current_manager":"Current manager","default_manager":"Default manager","field_not_found":"Field not found.","field_used_lock":"Detach this field from all content types before deleting it.","invalid_manager":"Invalid manager plugin.","no_content_types":"No content types found.","no_stored_values":"No values","type_name_exists":"This content type system name already exists.","back_to_structure":"Back to structure","edit_content_type":"Edit content type","edit_global_field":"Edit global field","field_definitions":"Fields","field_editor_help":"Create a global field definition and configure its first use in this content type.","field_name_exists":"This field system name already exists.","field_usage_count":"Used in {count} content types.","field_values_lock":"This field contains stored values and cannot be deleted.","invalid_direction":"Invalid direction.","invalid_type_data":"System name and title are required. Use lowercase Latin letters, digits and underscores.","manager_not_found":"Manager plugin not found.","save_content_type":"Save content type","type_has_children":"This content type has child types.","content_type_saved":"Content type saved.","delete_item_failed":"Failed to delete content item.","field_options_help":"For select fields: an array of objects with title and value keys.","field_options_json":"Select options (JSON)","field_still_in_use":"The field is attached to a content type and cannot be deleted.","global_field_saved":"Global field saved.","invalid_field_data":"System name and title are required. Use lowercase Latin letters, digits and underscores.","invalid_field_type":"Invalid field type.","confirm_delete_item":"Delete \\"{title}\\"? This action cannot be undone.","create_content_type":"Create content type","delete_content_type":"Delete content type","delete_field_failed":"Failed to delete the field.","field_parameter_max":"Maximum value","field_parameter_min":"Minimum value","invalid_parent_type":"Invalid parent content type.","manager_save_failed":"Failed to save the content type manager.","parent_content_type":"Parent content type","confirm_delete_field":"Delete this field globally? This action cannot be undone.","confirm_detach_field":"Remove this field from the structure? Its values in items of this content type will be permanently deleted.","content_type_deleted":"Content type deleted.","field_parameter_rows":"Visible rows","field_parameter_step":"Step","invalid_manager_mode":"Invalid manager update mode.","manage_type_managers":"Type managers","name_and_description":"Name and description","search_content_types":"Search content types…","back_to_content_types":"Back to content types","content_type_managers":"Content type managers","delete_field_globally":"Delete field globally","field_type_parameters":"Type parameters","global_field_settings":"Global field settings","invalid_field_options":"Field options must be a valid JSON array.","select_existing_field":"Select a field…","structure_editor_help":"Edit the content type and compose its field structure.","content_type_not_found":"Content type not found.","field_already_attached":"This field is already in the structure.","field_definitions_help":"Review and edit global field definitions, or remove fields that are no longer used.","field_not_in_structure":"This field is not part of the current structure.","field_parameter_latest":"Latest value","field_parameter_options":"Options (JSON)","invalid_field_parameter":"Invalid value for parameter {parameter}.","restore_default_manager":"Restore manifest default","field_identity_root_only":"Only Root can rename or change the type of a field shared with another manager.","field_parameter_earliest":"Earliest value","field_parameter_max_help":"Largest permitted numeric value.","field_parameter_min_help":"Smallest permitted numeric value.","field_parameter_required":"Parameter {parameter} is required.","global_field_editor_help":"These settings define the field globally and affect every content type that uses it.","field_attachment_settings":"Content type settings","field_identity_has_values":"A field containing values cannot change its system name or type.","field_parameter_rows_help":"Initial visible height of the multiline editor.","field_parameter_step_help":"Permitted increment between numeric values.","content_type_access_denied":"This content type is managed by another plugin.","content_type_managers_help":"Root-only assignment of the administrative plugin responsible for each content type.","delete_content_type_failed":"Failed to delete the content type.","field_parameter_max_length":"Maximum length","field_parameter_media_root":"Media root","field_parameter_min_length":"Minimum length","structure_operation_failed":"Failed to update the structure.","autocomplete_source_invalid":"Autocomplete source fields must be indexed text fields.","confirm_delete_content_type":"Delete \\"{title}\\"? This action cannot be undone.","field_parameter_latest_help":"Latest permitted date or partial date in the field format.","field_parameter_placeholder":"Placeholder","field_type_requires_indexed":"This field type requires indexing.","declarative_structure_notice":"This structure is declared by a plugin. Manual changes can be restored the next time that plugin is updated.","field_attachment_editor_help":"These settings apply only to this field in the current content type. Leave the title empty to use the global title.","field_parameter_media_accept":"Accepted media","field_parameter_options_help":"An array of objects with title or label and value keys.","invalid_field_parameter_json":"Parameter {parameter} must contain valid JSON.","field_parameter_content_types":"Allowed content types","field_parameter_earliest_help":"Earliest permitted date or partial date in the field format.","field_parameter_source_fields":"Suggestion source fields","multiple_boolean_not_supported":"Boolean fields cannot contain multiple values.","field_parameter_max_length_help":"Maximum permitted number of characters.","field_parameter_media_root_help":"Optional Media subfolder used as the logical root of the picker.","field_parameter_min_length_help":"Minimum permitted number of characters.","confirm_delete_field_with_values":"Delete \\"{title}\\" and all remaining stored values? This action cannot be undone.","field_parameter_placeholder_help":"Text displayed while the field is empty.","field_parameter_media_accept_help":"Optional comma-separated MIME/extension filters for the picker, for example image/*,pdf.","field_parameter_content_types_help":"Content types whose items can be selected in this field.","field_parameter_source_fields_help":"Indexed text fields whose existing values are offered as suggestions. Leave empty to use this field itself.","field_parameter_components":"Components","field_parameter_components_help":"Define the embedded value components owned by this compound field.","compound_components":"Components","compound_add_component":"Add component","compound_component_name":"Component name","compound_components_invalid":"Compound components are invalid.","remove":"Remove","compound_component_name_duplicate":"Compound component names must be unique.","compound_select_options_invalid":"Options must be a valid JSON array."},"handlers":{"view":{"title":"View content","actions":{"getItem":{"title":"Get content item"},"getType":{"title":"Get content type structure"},"listTypes":{"title":"List content types"},"searchItems":{"title":"Search content items"}}},"manage":{"title":"Manage content","actions":{"config":{"title":"Content settings"},"itemEdit":{"title":"Edit content item"},"itemList":{"title":"List content items"},"itemSave":{"title":"Save content item"},"typeEdit":{"title":"Edit content type"},"typeList":{"title":"List content types"},"typeSave":{"title":"Save content type"},"fieldEdit":{"title":"Edit content field"},"fieldList":{"title":"Manage global content fields"},"fieldMove":{"title":"Reorder content fields"},"fieldSave":{"title":"Save content field"},"itemDelete":{"title":"Delete content item"},"typeDelete":{"title":"Delete content type"},"fieldAttach":{"title":"Attach content field"},"fieldDelete":{"title":"Delete content field"},"fieldDetach":{"title":"Detach content field"},"globalFieldEdit":{"title":"Edit global content field"},"globalFieldSave":{"title":"Save global content field"},"typeManagerList":{"title":"Manage content type managers"},"typeManagerUpdate":{"title":"Update content type manager"}}},"content":{"title":"Edit content","actions":{"createItem":{"title":"Create content item"},"deleteItem":{"title":"Delete content item"},"updateItem":{"title":"Update content item"}}},"structure":{"title":"Edit content structure","actions":{"updateType":{"title":"Update content type"},"attachField":{"title":"Attach field"},"detachField":{"title":"Detach field"},"reorderFields":{"title":"Reorder fields"}}}},"description":"Create and manage content types and content items."}	draft	f	2026-08-28 16:29:02.013422+00
36	d875f2f6-b69e-4be9-a232-f42f71a3990e	en	{"title": "Static content", "description": "Reusable static content block."}	draft	f	2026-08-28 16:29:02.013422+00
37	e35594b9-184e-4c0f-bc2d-9eee9959b343	en	{"static_content_body": "<h2>From simple to complex—one model.</h2><p><img src=\\"/media/2026/08/stone_garden_2.webp\\"> This is a static HTML block. You can create, edit, and translate these blocks separately from the site's structure, and then add them to pages in the desired locations.</p>"}	draft	f	2026-08-29 13:46:59.396414+00
38	2f561283-698c-4989-b5ef-4a7cfdcea34d	en	{"static_content_body": "<h3>Independent content</h3><p>Each card in this grid is a separate content block. It can be edited and translated independently from the others.</p>"}	draft	f	2026-09-04 10:41:58.763239+00
39	66859df9-5e84-431e-9929-b7a19b9f2570	en	{"static_content_body": "<h3>One group</h3><p>The group simply brings several blocks together. Here, its template renders them as a responsive grid.</p>"}	draft	f	2026-09-04 10:42:56.825956+00
40	96de770c-a18f-4718-ac80-484075827ead	en	{"static_content_body": "<h3>Different presentation</h3><p>The same content can use another template without changing the data stored inside the blocks.</p>"}	draft	f	2026-09-04 10:47:28.802345+00
41	a659115d-b172-458e-974c-3e40d112d564	en	{"static_content_body": "<h3>Part of the page</h3><p>The page only decides where this group appears alongside other plugin instances. The content and its presentation remain separate.</p>"}	draft	f	2026-09-04 10:48:27.318315+00
42	2e764aae-9311-48f8-9cbf-53311da5346a	en	{"description": "<p>!</p>"}	draft	f	2026-08-28 16:29:02.013422+00
43	7c294eb7-4faf-4e7b-8d6e-cd85f04285c5	en	{"title":"Static Content Viewer","description":"Display a single static content item on the front end.","handlers":{"view":{"title":"Display static content","actions":{"view":{"title":"View static content"}},"instance_params":{"item_id":{"title":"Static content item","description":"Static content item to display."}}},"manage":{"title":"Manage static content","actions":{"config":{"title":"Static content settings"}}}},"phrases":{"incorrect_viewer_call":"The static content viewer was called with invalid parameters.","no_content":"No static content is available."}}	draft	f	2026-08-28 16:29:02.013422+00
44	7796764d-c048-4218-a240-055c9a123735	en	{"synonyms": [], "description": "<p>(short description may be here)</p>"}	draft	f	2026-08-28 16:29:02.013422+00
45	ebb5d529-1f9a-460c-b21b-fc1ac3c8c615	en	{"description": "<p><br></p>"}	draft	f	2026-08-28 16:29:02.013422+00
46	477d581f-5e84-41e9-9cf2-88041cdde000	en	{"synonyms": [], "description": "<p><br></p>"}	draft	f	2026-08-28 16:29:02.013422+00
47	83bf5c5e-0e28-4d2e-b213-15d234217ab7	en	{"title": "Publication of Research Paper on Phase II Clinical Trial Results of HGF Gene Therapy Product in the United States", "content": "<p><br></p>", "announce": "<p>This document announces the publication of Phase II clinical trial results for a hepatocyte growth factor (HGF) gene therapy product in the United States. The drug is intended for the treatment of critical limb ischemia. The release emphasizes the importance of these data for the further development and potential commercialization of the product, as well as its contribution to the field of therapeutic angiogenesis.</p><p><br></p><p>For more details on the published paper, please visit:</p><p>https://www.ahajournals.org/doi/abs/10.1161/CIRCINTERVENTIONS.125.015648</p>"}	draft	f	2026-08-28 16:29:02.013422+00
48	6736540f-3bb7-407a-8cf8-19c4963369f2	en	{"description": "<p><br></p>"}	draft	f	2026-08-28 16:29:02.013422+00
49	f7fc9dd3-cbcc-44c7-bc2d-593f2a781284	en	{"description": "<p><br></p>"}	draft	f	2026-08-28 16:29:02.013422+00
50	5cc9f90d-1e53-40cc-8de7-f4685c8e781e	en	{"description": "<p><br></p>"}	draft	f	2026-08-28 16:29:02.013422+00
51	f66a82fb-db42-448f-9263-67e72c11ec2d	en	{"description": "<p><br></p>"}	draft	f	2026-08-28 16:29:02.013422+00
52	05872f1c-9b1c-49a0-a119-d25212115346	en	{"description": "<p><br></p>"}	draft	f	2026-08-28 16:29:02.013422+00
53	e28b811b-9773-4bfa-a3c6-e769ec24689f	en	{"description": "<p><br></p>"}	draft	f	2026-08-28 16:29:02.013422+00
54	f517cf05-90bf-4416-8ba4-d999f514c574	en	{"description": "<p><br></p>"}	draft	f	2026-08-28 16:29:02.013422+00
55	49251ef3-4738-46af-8160-4b13b0150036	en	{"description": "<p><br></p>"}	draft	f	2026-08-28 16:29:02.013422+00
56	ce4b1f5a-8331-483f-af0f-234309c7a909	en	{"description": "<p><br></p>"}	draft	f	2026-08-28 16:29:02.013422+00
57	dd32137d-4697-4478-b09f-8e8608ab2599	en	{"description": "<p><br></p>"}	draft	f	2026-08-28 16:29:02.013422+00
58	b63e3bc4-32e7-4ca7-84cc-7567ab75b207	en	{"description": "<p><br></p>"}	draft	f	2026-08-28 16:29:02.013422+00
59	af0d7fac-b06d-4078-8404-c27724633fe4	en	{"description": "<p><br></p>"}	draft	f	2026-08-28 16:29:02.013422+00
60	1c432a3a-77ef-4639-8f97-de37e47239c7	en	{"description": "<p><br></p>"}	draft	f	2026-08-28 16:29:02.013422+00
61	79a78726-ea73-4e05-a96b-a5bbe8c3f159	en	{"title": "American Society of Gene+Cell Therapy published Quarterly Industry Landscape Report (Q3 2025)", "content": "<p><br></p>", "announce": "<p>The report examines new approvals across each of the gene, cell, and RNA categories that took place in Q3 2025, and new initiated trials.</p>"}	draft	f	2026-08-28 16:29:02.013422+00
62	bbf7c42e-9e6b-4ee5-b20c-4d15a65565cb	en	{"item_title": "Home"}	draft	f	2026-01-30 20:09:35.688355+00
63	a3c1739e-94a9-4397-b54b-3084242ee292	en	{"item_title": "About"}	draft	f	2026-01-30 20:09:35.691368+00
64	8c05bbc4-1aad-43f5-8592-923c25976eb2	en	{"title": "User Profile", "phrases": {"save": "Save", "email": "Email address", "guest": "Guest", "login": "Sign in", "logout": "Log out", "connect": "Connect", "profile": "Profile", "replace": "Replace", "website": "Website", "welcome": "Welcome,", "password": "Password", "register": "Register", "username": "Username", "verified": "Verified", "connected": "Connected", "configured": "Configured", "disconnect": "Disconnect", "email_same": "The new email address is the same as the current address.", "credentials": "Credentials", "email_taken": "This email address is already in use.", "preferences": "Preferences", "change_email": "Change email", "display_name": "Display name", "new_password": "New password", "not_verified": "Not verified", "save_changes": "Save changes", "set_password": "Set password", "email_invalid": "Enter a valid email address.", "not_connected": "Not connected", "password_help": "Use a password as one of the available sign-in methods for this account.", "pending_email": "Pending verification", "username_help": "Letters, numbers, dots, underscores, and hyphens.", "email_required": "Enter an email address.", "not_configured": "Not configured", "password_short": "Password must be at least 8 characters long.", "username_taken": "This username is already in use.", "change_password": "Change password", "profile_updated": "Your profile has been updated.", "remove_password": "Remove password", "repeat_password": "Repeat new password", "sign_in_methods": "Sign-in methods", "current_password": "Current password", "last_auth_method": "The last sign-in method cannot be removed.", "password_changed": "Password updated.", "password_removed": "Password sign-in removed.", "username_changed": "Username updated.", "username_invalid": "Invalid username format.", "credentials_intro": "Manage the credentials and sign-in methods for your account.", "email_change_help": "Your current address remains active until the new address is confirmed.", "email_change_sent": "A confirmation link was sent to the new email address.", "no_public_profile": "This user does not have a public profile.", "password_mismatch": "Passwords do not match.", "password_required": "Enter a password.", "username_required": "Enter a username.", "disconnect_confirm": "Disconnect this sign-in method?", "pending_email_help": "A confirmation link was sent to this address.", "remove_password_help": "Password sign-in will be removed. Another sign-in method must remain connected.", "sign_in_methods_help": "Connect, replace, or disconnect external authentication providers.", "provider_disconnected": "Authentication provider disconnected.", "credentials_load_failed": "Credentials could not be loaded.", "provider_used_elsewhere": "This Google account is already connected to another user.", "current_password_invalid": "Current password is incorrect.", "credentials_action_failed": "The requested account change could not be completed.", "provider_already_connected": "A Google account is already connected. Use Replace to change it."}, "handlers": {"view": {"title": "Display profile", "actions": {"statusbar": {"title": "User status bar"}, "profile_page": {"title": "Profile page"}}}, "manage": {"title": "Manage profiles", "actions": {"config": {"title": "Profile settings"}}}, "account": {"title": "Account", "actions": {"credentials": {"title": "Credentials"}, "change_email": {"title": "Change email"}, "change_password": {"title": "Change password"}, "change_username": {"title": "Change username"}, "remove_password": {"title": "Remove password"}, "disconnect_provider": {"title": "Disconnect provider"}}}}, "settings": {"profile_page_enabled": {"title": "Profile page enabled", "description": "Allows users to have a public profile page on this domain."}}, "description": "Manage user profile data, credentials, and account preferences."}	draft	f	2026-08-28 16:29:02.013422+00
65	5c2f8d58-b584-44e4-b71a-97fecc2512ea	en	{"title": "Profile", "description": "Public user profile data."}	draft	f	2026-08-28 16:29:02.013422+00
66	ec836f0c-9db5-418a-92e3-395ad5c15dcd	en	{"title": "Display name", "description": "Public name displayed for the user."}	draft	f	2026-08-28 16:29:02.013422+00
67	d2b1806c-287d-4800-b734-6b3e472d0a03	en	{"title": "Website", "description": ""}	draft	f	2026-08-28 16:29:02.013422+00
68	454cc519-f103-4a4d-adbe-2bd183093daa	en	{"title": "Display name", "description": ""}	draft	f	2026-08-28 16:29:02.013422+00
69	1dec29c1-3552-4ee6-ab7d-21f0677205ec	en	{"title": "Website", "description": "Public website URL."}	draft	f	2026-08-28 16:29:02.013422+00
70	e892c531-2e17-4913-8ff4-9222d0b3db08	en	{"title": "User Account", "phrases": {"email": "Email address", "guest": "Guest", "hello": "Hello", "login": "Sign in", "ignore": "If you did not create this account, you can safely ignore this email.", "logout": "Log out", "welcome": "Welcome,", "password": "Password", "register": "Register", "thankyou": "Thank you for creating an account.", "username": "Username", "login_help": "Enter your username or email address.", "email_taken": "An account with this email already exists.", "remember_me": "Remember me", "invalid_data": "Some of the information provided is invalid. Please check and try again.", "login_banned": "Your account has been suspended.", "login_failed": "Incorrect username or password.", "login_locked": "Your account has been locked due to too many failed sign-in attempts.", "new_password": "New password", "or_copy_link": "If the button does not work, copy and paste this link into your browser:", "please_click": "Please confirm your email address by clicking the button below.", "back_to_login": "Back to sign in", "email_invalid": "Please enter a valid email address.", "login_expired": "Your session has expired. Please sign in again.", "login_success": "You have signed in successfully.", "password_weak": "Password must include at least one uppercase letter, one number, and one special character.", "unknown_error": "An unknown error occurred. Please try again later.", "username_help": "Letters, numbers, dots (.), underscores (_), or hyphens (-). No spaces or “@”.", "email_required": "Please enter your email address.", "email_verified": "Your email address has been verified successfully.", "fields_missing": "Please fill in all required fields.", "login_inactive": "Your account is not active. Access requires administrator approval.", "login_required": "Please enter your sign-in credentials.", "logout_success": "You have signed out successfully.", "password_short": "Password must be at least 8 characters long.", "reset_password": "Reset password", "return_to_site": "Return to site", "username_taken": "This username is already in use.", "account_deleted": "Your account has been deleted successfully.", "forgot_password": "Forgot password?", "login_not_found": "No account was found with these credentials.", "repeat_password": "Repeat password", "login_unverified": "Please verify your email address before signing in.", "username_invalid": "Invalid username format.", "login_2fa_invalid": "Invalid verification code.", "password_mismatch": "Passwords don’t match.", "password_required": "Please enter a password.", "too_many_attempts": "Too many registration attempts. Please wait a few minutes and try again.", "username_or_email": "Username or email", "username_required": "Please enter a username.", "verification_code": "Verification code", "verification_sent": "A verification email has been sent to your address.", "verify_your_email": "Verify your email address", "action_not_allowed": "You are not allowed to perform this action.", "email_change_intro": "A request was made to use this email address for your account.", "login_2fa_required": "Two-factor authentication is required.", "server_unreachable": "The server is temporarily unavailable. Please try again later.", "terms_not_accepted": "You must accept the terms and conditions to continue.", "email_change_button": "Confirm new email address", "email_change_ignore": "If you did not request this change, you can safely ignore this email.", "email_register_help": "We’ll send a confirmation link to this address.", "password_reset_sent": "Password reset instructions have been sent to your email.", "redirecting_to_site": "You will be returned to the site automatically in a few seconds.", "registration_failed": "Something went wrong. Please try again later.", "resend_verification": "Resend verification email", "verification_failed": "Email verification failed.", "verify_email_button": "Verify email address", "continue_with_google": "Continue with Google", "email_change_confirm": "Confirm the change by using the link below. Your current email address will remain active until you confirm.", "email_change_subject": "Confirm your new email address", "email_change_success": "Your email address has been changed and verified successfully.", "email_changed_notice": "The email address for your account was changed to:", "registration_success": "Your account has been created successfully.", "repeat_password_help": "Enter the same password again.", "verification_expired": "The verification link has expired.", "verification_invalid": "The verification link is invalid.", "verification_success": "Your email address has been verified successfully.", "email_change_conflict": "This email address is already used by another account.", "password_reset_failed": "Password reset failed. Please try again later.", "password_reset_ignore": "If you did not request a password reset, you can safely ignore this email. Your current password will remain unchanged.", "account_inactive_title": "Account inactive", "password_register_help": "Use at least 8 characters.", "password_reset_success": "Your password has been updated successfully.", "profile_update_success": "Your profile has been updated successfully.", "password_case_sensitive": "Passwords are case-sensitive.", "verification_email_sent": "A verification email has been sent to your address.", "account_inactive_message": "This account is not active. Access requires administrator approval.", "email_change_error_title": "Email change failed", "password_reset_form_help": "Choose a new password. Your current password will remain valid until you submit this form successfully.", "send_password_reset_link": "Send password reset link", "verification_error_title": "Verification failed", "verification_email_resent": "A new verification email has been sent.", "email_change_success_title": "Email address changed", "password_reset_email_intro": "We received a request to reset your password. Use the link below within one hour to choose a new password.", "password_reset_error_title": "Password reset failed", "verification_email_subject": "Verify your email address", "verification_resend_prompt": "Enter the email address for the account and we will send a new verification link.", "verification_success_login": "Your email address has been verified and you are now signed in.", "verification_success_title": "Email verified", "password_reset_request_help": "Enter your username or email address. If password reset is available, we’ll send a link to your email.", "verification_resend_neutral": "If this address belongs to an account that still requires verification, a new verification email has been sent.", "email_changed_notice_subject": "Your email address was changed", "password_reset_email_subject": "Reset your password", "verification_invalid_or_used": "The verification link is invalid or has already been used.", "verification_resend_too_soon": "A verification email was sent recently. Please wait before requesting another one.", "email_changed_notice_security": "If you did not make this change, contact the site administrator immediately.", "registration_pending_approval": "Your account has been created and is awaiting administrator approval.", "password_reset_request_neutral": "If this address belongs to an account that can reset its password, a reset link has been sent.", "verification_already_completed": "Your email address has already been verified.", "email_change_invalid_or_expired": "The email change link is invalid, expired, or has already been used.", "password_reset_invalid_or_expired": "The password reset link is invalid, expired, or has already been used."}, "handlers": {"view": {"title": "Account interface", "actions": {"login": {"title": "Sign in"}, "register": {"title": "Register"}, "loginform": {"title": "Sign-in and registration form"}, "reset_password": {"title": "Reset password"}, "resend_verification": {"title": "Resend verification email"}, "request_password_reset": {"title": "Request password reset"}}}, "manage": {"title": "Manage accounts", "actions": {"config": {"title": "Account settings"}}}}, "settings": {"login_action": {"title": "Action after sign-in", "options": {"reload": "Reload current page", "nothing": "Do nothing", "redirect": "Redirect to URL"}, "description": "Defines what happens after a successful sign-in."}, "redirect_page": {"title": "Redirect page URL", "description": "URL used after sign-in when the redirect action is selected."}, "two_factor_auth": {"title": "Two-factor authentication", "description": "Enables two-factor authentication for supported sign-in flows."}, "email_activation": {"title": "Email activation required", "description": "Requires email verification for users who register with email and password."}, "sso_authentication": {"title": "SSO authentication", "description": "Allows users to authenticate through the main domain using single sign-on."}, "registration_enabled": {"title": "Registration enabled", "description": "Allows new users to register on this domain."}, "admin_activation_required": {"title": "Administrator activation required", "description": "Keeps newly registered accounts inactive until an administrator activates them."}}, "description": "User registration, authentication, credential management, and account-related actions."}	draft	f	2026-08-28 16:29:02.013422+00
71	e892c531-2e17-4913-8ff4-9222d0b3db08	uk	{"title": "Акаунт користувача", "phrases": {"email": "Електронна адреса", "guest": "Гість", "hello": "Вітаємо", "login": "Увійти", "ignore": "Якщо ви не створювали цей акаунт, просто проігноруйте цей лист.", "logout": "Вийти", "welcome": "Ласкаво просимо,", "password": "Пароль", "register": "Зареєструватися", "thankyou": "Дякуємо за створення акаунта.", "username": "Ім’я користувача", "login_help": "Введіть ім’я користувача або електронну адресу.", "email_taken": "Акаунт із такою електронною адресою вже існує.", "remember_me": "Запам’ятати мене", "invalid_data": "Деякі введені дані некоректні. Перевірте їх і спробуйте ще раз.", "login_banned": "Ваш акаунт заблоковано.", "login_failed": "Неправильне ім’я користувача або пароль.", "login_locked": "Ваш акаунт заблоковано через надто велику кількість невдалих спроб входу.", "new_password": "Новий пароль", "or_copy_link": "Якщо кнопка не працює, скопіюйте та вставте це посилання у браузер:", "please_click": "Підтвердьте електронну адресу, натиснувши кнопку нижче.", "back_to_login": "Назад до входу", "email_invalid": "Введіть коректну електронну адресу.", "login_expired": "Сеанс завершився. Увійдіть знову.", "login_success": "Ви успішно увійшли.", "password_weak": "Пароль має містити щонайменше одну велику літеру, одну цифру та один спеціальний символ.", "unknown_error": "Сталася невідома помилка. Спробуйте пізніше.", "username_help": "Літери, цифри, крапки (.), підкреслення (_) або дефіси (-). Без пробілів і символу «@».", "email_required": "Введіть електронну адресу.", "email_verified": "Вашу електронну адресу успішно підтверджено.", "fields_missing": "Заповніть усі обов’язкові поля.", "login_inactive": "Ваш акаунт не активовано. Для доступу потрібне підтвердження адміністратора.", "login_required": "Введіть дані для входу.", "logout_success": "Ви успішно вийшли.", "password_short": "Пароль має містити щонайменше 8 символів.", "reset_password": "Відновити пароль", "return_to_site": "Повернутися на сайт", "username_taken": "Це ім’я користувача вже використовується.", "account_deleted": "Ваш акаунт успішно видалено.", "forgot_password": "Забули пароль?", "login_not_found": "Акаунт із такими даними не знайдено.", "repeat_password": "Повторіть пароль", "login_unverified": "Підтвердьте електронну адресу перед входом.", "username_invalid": "Некоректний формат імені користувача.", "login_2fa_invalid": "Неправильний код підтвердження.", "password_mismatch": "Паролі не збігаються.", "password_required": "Введіть пароль.", "too_many_attempts": "Забагато спроб реєстрації. Зачекайте кілька хвилин і спробуйте ще раз.", "username_or_email": "Ім’я користувача або email", "username_required": "Введіть ім’я користувача.", "verification_code": "Код підтвердження", "verification_sent": "Лист для підтвердження надіслано на вашу адресу.", "verify_your_email": "Підтвердьте електронну адресу", "action_not_allowed": "Вам заборонено виконувати цю дію.", "email_change_intro": "Надійшов запит на використання цієї електронної адреси для вашого акаунта.", "login_2fa_required": "Потрібна двофакторна автентифікація.", "server_unreachable": "Сервер тимчасово недоступний. Спробуйте пізніше.", "terms_not_accepted": "Щоб продовжити, потрібно прийняти умови використання.", "email_change_button": "Підтвердити нову електронну адресу", "email_change_ignore": "Якщо ви не запитували цю зміну, просто проігноруйте цей лист.", "email_register_help": "На цю адресу буде надіслано посилання для підтвердження.", "password_reset_sent": "Інструкції з відновлення пароля надіслано на вашу електронну адресу.", "redirecting_to_site": "За кілька секунд ви автоматично повернетеся на сайт.", "registration_failed": "Щось пішло не так. Спробуйте пізніше.", "resend_verification": "Надіслати лист повторно", "verification_failed": "Не вдалося підтвердити електронну адресу.", "verify_email_button": "Підтвердити email", "continue_with_google": "Продовжити з Google", "email_change_confirm": "Підтвердьте зміну за посиланням нижче. Поточна електронна адреса залишатиметься активною до підтвердження.", "email_change_subject": "Підтвердіть нову електронну адресу", "email_change_success": "Вашу електронну адресу успішно змінено та підтверджено.", "email_changed_notice": "Електронну адресу вашого акаунта змінено на:", "registration_success": "Ваш акаунт успішно створено.", "repeat_password_help": "Введіть той самий пароль ще раз.", "verification_expired": "Посилання для підтвердження втратило чинність.", "verification_invalid": "Посилання для підтвердження недійсне.", "verification_success": "Вашу електронну адресу успішно підтверджено.", "email_change_conflict": "Ця електронна адреса вже використовується іншим акаунтом.", "password_reset_failed": "Не вдалося змінити пароль. Спробуйте пізніше.", "password_reset_ignore": "Якщо ви не запитували скидання пароля, просто проігноруйте цей лист. Поточний пароль залишиться без змін.", "account_inactive_title": "Акаунт не активовано", "password_register_help": "Використайте щонайменше 8 символів.", "password_reset_success": "Ваш пароль успішно змінено.", "profile_update_success": "Ваш профіль успішно оновлено.", "password_case_sensitive": "Пароль чутливий до регістру.", "verification_email_sent": "Лист для підтвердження надіслано на вашу адресу.", "account_inactive_message": "Цей акаунт неактивний. Для доступу потрібне підтвердження адміністратора.", "email_change_error_title": "Не вдалося змінити email", "password_reset_form_help": "Введіть новий пароль. Поточний пароль залишатиметься чинним, доки ви успішно не надішлете цю форму.", "send_password_reset_link": "Надіслати посилання для відновлення", "verification_error_title": "Не вдалося підтвердити email", "verification_email_resent": "Новий лист для підтвердження надіслано.", "email_change_success_title": "Електронну адресу змінено", "password_reset_email_intro": "Ми отримали запит на скидання вашого пароля. Скористайтеся посиланням нижче протягом однієї години, щоб встановити новий пароль.", "password_reset_error_title": "Не вдалося скинути пароль", "verification_email_subject": "Підтвердьте електронну адресу", "verification_resend_prompt": "Введіть email акаунта, і ми надішлемо нове посилання для підтвердження.", "verification_success_login": "Email підтверджено, і ви вже увійшли до свого акаунта.", "verification_success_title": "Email підтверджено", "password_reset_request_help": "Введіть ім’я користувача або email. Якщо відновлення пароля доступне, ми надішлемо посилання на вашу пошту.", "verification_resend_neutral": "Якщо ця адреса належить акаунту, який ще потребує підтвердження, новий лист для верифікації надіслано.", "email_changed_notice_subject": "Електронну адресу вашого акаунта змінено", "password_reset_email_subject": "Скидання пароля", "verification_invalid_or_used": "Посилання для підтвердження недійсне або вже було використане.", "verification_resend_too_soon": "Лист для підтвердження вже нещодавно надсилали. Зачекайте перед повторною спробою.", "email_changed_notice_security": "Якщо ви не виконували цю зміну, негайно зверніться до адміністратора сайту.", "registration_pending_approval": "Ваш акаунт створено й він очікує на активацію адміністратором.", "password_reset_request_neutral": "Якщо ця адреса належить акаунту, для якого доступне відновлення пароля, посилання для скидання вже надіслано.", "verification_already_completed": "Вашу електронну адресу вже підтверджено.", "email_change_invalid_or_expired": "Посилання для зміни email недійсне, прострочене або вже було використане.", "password_reset_invalid_or_expired": "Посилання для скидання пароля недійсне, прострочене або вже було використане."}, "handlers": {"view": {"title": "Інтерфейс акаунта", "actions": {"login": {"title": "Увійти"}, "register": {"title": "Реєстрація"}, "loginform": {"title": "Форма входу та реєстрації"}, "reset_password": {"title": "Зміна пароля"}, "resend_verification": {"title": "Повторне надсилання верифікації"}, "request_password_reset": {"title": "Запит на відновлення пароля"}}}, "manage": {"title": "Керування акаунтами", "actions": {"config": {"title": "Налаштування акаунтів"}}}}, "settings": {"login_action": {"title": "Дія після входу", "options": {"reload": "Перезавантажити поточну сторінку", "nothing": "Нічого не робити", "redirect": "Перейти за URL"}, "description": "Визначає, що відбувається після успішного входу."}, "redirect_page": {"title": "URL сторінки перенаправлення", "description": "URL для переходу після входу, коли обрано відповідну дію."}, "two_factor_auth": {"title": "Двофакторна автентифікація", "description": "Вмикає двофакторну автентифікацію для підтримуваних сценаріїв входу."}, "email_activation": {"title": "Потрібне підтвердження email", "description": "Вимагає підтвердження електронної адреси для користувачів, які реєструються за допомогою email і пароля."}, "sso_authentication": {"title": "SSO-автентифікація", "description": "Дозволяє користувачам входити через головний домен за допомогою єдиного входу."}, "registration_enabled": {"title": "Реєстрацію увімкнено", "description": "Дозволяє новим користувачам реєструватися на цьому домені."}, "admin_activation_required": {"title": "Потрібна активація адміністратором", "description": "Нові акаунти залишаються неактивними, доки адміністратор їх не активує."}}, "description": "Реєстрація, автентифікація, керування обліковими даними та діями з акаунтом."}	draft	f	2026-08-28 16:29:02.013422+00
72	b485a84e-abb0-4f35-afbb-bff0877f2611	uk	{"title": "Текст", "description": "Базовий кореневий тип для текстових значень."}	draft	f	2026-08-28 16:29:02.013422+00
73	10bc994f-613b-403f-8325-5c45d936d85b	uk	{"title": "Число", "description": "Базовий кореневий тип для числових значень."}	draft	f	2026-08-28 16:29:02.013422+00
74	97c33e18-0fe1-4f1b-af2d-8c23180efe3e	uk	{"title": "Логічне значення", "description": "Базовий тип для значень true або false."}	draft	f	2026-08-28 16:29:02.013422+00
75	773bd1a9-af28-401f-98ae-bcf67fbfe00d	uk	{"title": "Дата", "description": "Базовий кореневий тип для календарних і часових значень."}	draft	f	2026-08-28 16:29:02.013422+00
76	b67f96c4-e8a3-4639-ad97-f03edb669a10	uk	{"title": "Рядок", "description": "Короткий однорядковий текст без складного форматування."}	draft	f	2026-08-28 16:29:02.013422+00
77	b67f96c4-e8a3-4639-ad97-f03edb669a10	en	{"title": "String", "description": "Short single-line plain text value without complex formatting."}	draft	f	2026-08-28 16:29:02.013422+00
78	217f9ade-fad3-408d-b6dc-689a792cfc3e	uk	{"title": "Багаторядковий текст", "description": "Текстове поле для довших значень із кількома рядками без складного форматування."}	draft	f	2026-08-28 16:29:02.013422+00
79	217f9ade-fad3-408d-b6dc-689a792cfc3e	en	{"title": "Multiline text", "description": "Text field for longer plain-text values containing multiple lines."}	draft	f	2026-08-28 16:29:02.013422+00
80	00992fd8-c284-4c17-8676-00ad240c71f1	uk	{"title": "Форматований текст", "description": "Текстове поле для вмісту з форматуванням."}	draft	f	2026-08-28 16:29:02.013422+00
81	00992fd8-c284-4c17-8676-00ad240c71f1	en	{"title": "Rich text", "description": "Text field for formatted content."}	draft	f	2026-08-28 16:29:02.013422+00
82	14580f8b-da3c-47e6-94b4-a05a75aa0d1f	uk	{"title": "Email", "description": "Текстове поле для адрес електронної пошти."}	draft	f	2026-08-28 16:29:02.013422+00
83	14580f8b-da3c-47e6-94b4-a05a75aa0d1f	en	{"title": "Email", "description": "Text field intended for email addresses."}	draft	f	2026-08-28 16:29:02.013422+00
84	90a3d1d4-e570-4df2-a91e-b8ba48e6930f	uk	{"title": "URL", "description": "Текстове поле для веб-адрес і посилань."}	draft	f	2026-08-28 16:29:02.013422+00
85	90a3d1d4-e570-4df2-a91e-b8ba48e6930f	en	{"title": "URL", "description": "Text field intended for web addresses and links."}	draft	f	2026-08-28 16:29:02.013422+00
86	fde86f13-d94b-4549-9551-625bd249e903	uk	{"title": "Slug", "description": "Короткий текстовий ідентифікатор для URL, ключів або системних назв."}	draft	f	2026-08-28 16:29:02.013422+00
87	fde86f13-d94b-4549-9551-625bd249e903	en	{"title": "Slug", "description": "Short textual identifier for URLs, keys, or system names."}	draft	f	2026-08-28 16:29:02.013422+00
88	b3596657-c099-47a0-8277-0d7d9d305034	uk	{"title": "Ціле число", "description": "Числове поле для цілих значень без дробової частини."}	draft	f	2026-08-28 16:29:02.013422+00
89	b3596657-c099-47a0-8277-0d7d9d305034	en	{"title": "Integer", "description": "Numeric field for whole numbers without a fractional part."}	draft	f	2026-08-28 16:29:02.013422+00
90	3d845d0c-da0b-4ee6-bd2a-34e1b7d355ce	uk	{"title": "Десяткове число", "description": "Числове поле для точних дробових значень."}	draft	f	2026-08-28 16:29:02.013422+00
91	3d845d0c-da0b-4ee6-bd2a-34e1b7d355ce	en	{"title": "Decimal", "description": "Numeric field for precise fractional values."}	draft	f	2026-08-28 16:29:02.013422+00
92	7d3b76f9-0b30-48ad-a461-e6ff54b52f01	uk	{"title": "Рік", "description": "Часткова дата, що містить лише рік."}	draft	f	2026-08-28 16:29:02.013422+00
93	7d3b76f9-0b30-48ad-a461-e6ff54b52f01	en	{"title": "Year", "description": "Partial date containing only a year."}	draft	f	2026-08-28 16:29:02.013422+00
94	8d2bb672-f0a0-429e-baa5-ccbd3c49d67d	uk	{"title": "Рік і місяць", "description": "Часткова дата, що містить рік і місяць без конкретного дня."}	draft	f	2026-08-28 16:29:02.013422+00
95	8d2bb672-f0a0-429e-baa5-ccbd3c49d67d	en	{"title": "Year and month", "description": "Partial date containing year and month without a specific day."}	draft	f	2026-08-28 16:29:02.013422+00
96	8fdf4116-4f22-4dc0-85d8-bc264a6f1834	uk	{"title": "Повна дата", "description": "Календарна дата з роком, місяцем і днем без часу доби."}	draft	f	2026-08-28 16:29:02.013422+00
97	8fdf4116-4f22-4dc0-85d8-bc264a6f1834	en	{"title": "Full date", "description": "Calendar date with year, month, and day, without time of day."}	draft	f	2026-08-28 16:29:02.013422+00
98	f5ba0433-36f4-4a83-a6f3-f604bff81452	uk	{"title": "Дата і час", "description": "Повне значення дати й часу для подій, публікацій, логів та інших часово-чутливих даних."}	draft	f	2026-08-28 16:29:02.013422+00
99	f5ba0433-36f4-4a83-a6f3-f604bff81452	en	{"title": "Date and time", "description": "Full date and time value for events, publications, logs, and other time-sensitive data."}	draft	f	2026-08-28 16:29:02.013422+00
100	ce4d481a-cf9d-4f7c-95e4-68dea6d26be9	uk	{"title": "Випадаючий список", "description": "Рядкове поле для вибору одного значення зі списку варіантів. Значення option може бути не лише числовим."}	draft	f	2026-08-28 16:29:02.013422+00
101	ce4d481a-cf9d-4f7c-95e4-68dea6d26be9	en	{"title": "Select", "description": "String-based field for selecting one value from a list of options. Option values are not limited to numbers."}	draft	f	2026-08-28 16:29:02.013422+00
102	ba1e3f78-1fd7-4044-9b36-00a8173fe6c1	uk	{"title": "ID домену", "description": "Цілочисельний ідентифікатор домену."}	draft	f	2026-08-28 16:29:02.013422+00
103	ba1e3f78-1fd7-4044-9b36-00a8173fe6c1	en	{"title": "Domain ID", "description": "Integer identifier of a domain."}	draft	f	2026-08-28 16:29:02.013422+00
104	9a56eb7d-aadf-4044-85a7-85242caddf4e	uk	{"title": "ID сторінки", "description": "Цілочисельний ідентифікатор сторінки."}	draft	f	2026-08-28 16:29:02.013422+00
105	9a56eb7d-aadf-4044-85a7-85242caddf4e	en	{"title": "Page ID", "description": "Integer identifier of a page."}	draft	f	2026-08-28 16:29:02.013422+00
106	ddb21e75-0c06-4f5d-a2ae-a2c35dacfeae	uk	{"title": "ID типу контенту", "description": "Цілочисельний ідентифікатор типу контенту."}	draft	f	2026-08-28 16:29:02.013422+00
107	ddb21e75-0c06-4f5d-a2ae-a2c35dacfeae	en	{"title": "Content type ID", "description": "Integer identifier of a content type."}	draft	f	2026-08-28 16:29:02.013422+00
108	4f4fb317-c7c3-47db-8c14-7805f2b33213	uk	{"title": "ID типу поля", "description": "Цілочисельний ідентифікатор типу поля."}	draft	f	2026-08-28 16:29:02.013422+00
109	4f4fb317-c7c3-47db-8c14-7805f2b33213	en	{"title": "Field type ID", "description": "Integer identifier of a field type."}	draft	f	2026-08-28 16:29:02.013422+00
110	66e8205b-e0fd-4ce4-ad74-352da9c5f56a	uk	{"title": "ID поля", "description": "Цілочисельний ідентифікатор поля."}	draft	f	2026-08-28 16:29:02.013422+00
111	66e8205b-e0fd-4ce4-ad74-352da9c5f56a	en	{"title": "Field ID", "description": "Integer identifier of a field."}	draft	f	2026-08-28 16:29:02.013422+00
112	a1fd8d38-d28c-4bff-9511-d93940a0c49f	uk	{"title": "ID елемента", "description": "Цілочисельний ідентифікатор елемента контенту."}	draft	f	2026-08-28 16:29:02.013422+00
113	a1fd8d38-d28c-4bff-9511-d93940a0c49f	en	{"title": "Item ID", "description": "Integer identifier of a content item."}	draft	f	2026-08-28 16:29:02.013422+00
114	7e1a5820-7247-4a83-b5b7-fa45518cda52	uk	{"title": "Прапорець", "description": "Логічне поле, що вводиться через checkbox."}	draft	f	2026-08-28 16:29:02.013422+00
115	7e1a5820-7247-4a83-b5b7-fa45518cda52	en	{"title": "Checkbox", "description": "Boolean field rendered as a checkbox input."}	draft	f	2026-08-28 16:29:02.013422+00
116	294fba83-f58b-48e1-a213-381354cf3415	uk	{"title": "Так/Ні", "description": "Логічне поле, що вводиться як вибір між значеннями Так і Ні."}	draft	f	2026-08-28 16:29:02.013422+00
117	294fba83-f58b-48e1-a213-381354cf3415	en	{"title": "Yes/No", "description": "Boolean field rendered as a Yes/No choice."}	draft	f	2026-08-28 16:29:02.013422+00
118	bb9a7b9d-dbb4-4bd2-bfd2-c8158bb06ed3	en	{"title": null, "tour_status": "new"}	draft	f	2026-08-28 16:29:02.013422+00
119	ed5c06cd-a276-4e6f-986a-66e73e7a4df8	en	{"title": "bet_user_5_fixture_1529023_goals_answer_0_0", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
120	ba38b855-68b7-403d-96b2-558f624d4499	en	{"title": "bet_user_5_fixture_1529023_goals_answer_1_1", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
121	c7bfc632-e21c-435f-818e-da591074a35c	en	{"title": "bet_user_5_fixture_1529023_goals_answer_2_1", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
122	5dcc775e-d0e8-4876-bf08-1d4f53d94463	en	{"title": "bet_user_5_fixture_1529023_goals_answer_3_0", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
123	62e96f97-37ac-4712-9b19-e118651a7f77	en	{"title": "bet_user_5_fixture_1529023_goals_answer_4_0", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
124	19304d7f-26fc-4c20-8599-cb6fe890b87a	en	{"title": "bet_user_5_fixture_1529023_goals_answer_5_0", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
125	11460050-06db-41f5-96df-3554d2577837	en	{"title": "bet_user_5_fixture_1529023_goals_answer_6_0", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
126	7e6c546e-6b0f-4bb4-bb54-54f3afa27256	en	{"title": "bet_user_5_fixture_1529023_goals_0_0", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
127	f014f175-16fe-404f-8721-8979b1d7d2a2	en	{"title": "bet_user_5_fixture_1529023_goals_1_1", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
128	93f34ae9-42cb-4c42-9c7f-c0e864b9aa3e	en	{"title": "bet_user_5_fixture_1529023_goals_2_1", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
129	bd59844f-30da-4ccb-9843-5c02a3046fff	en	{"title": "bet_user_5_fixture_1529023_goals_3_0", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
130	c78d3d95-ec17-46a6-9a18-aa6cf581a6e3	en	{"title": "bet_user_5_fixture_1529023_goals_4_0", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
131	edcc7296-6210-4037-96cd-65239d719b71	en	{"title": "bet_user_5_fixture_1529023_goals_5_0", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
132	e22b53b3-6b84-4f37-8026-44fe8503a469	en	{"title": "bet_user_5_fixture_1529023_goals_6_0", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
133	2821132c-ac57-4f8b-8154-b30834aa4e8a	en	{"title": "bet_user_5_fixture_1529023_goals_0_0", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
134	3bcee096-4715-435a-8356-3ce0716b4c02	en	{"title": "bet_user_5_fixture_1529023_goals_1_1", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
135	8c9a741e-94b8-4abb-9f73-af36b3d802ec	en	{"title": "bet_user_5_fixture_1529023_goals_2_1", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
136	88089880-d460-4d58-9fbb-0b0381c9afaf	en	{"title": "bet_user_5_fixture_1529023_goals_3_0", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
137	3140effe-60c3-4232-ab90-7e57aa1da5ba	en	{"title": "bet_user_5_fixture_1529023_goals_4_0", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
138	9932506a-9e7c-4a6a-af41-39e31f88ba0d	en	{"title": "bet_user_5_fixture_1529023_goals_5_0", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
139	1279c3ba-28b4-4894-8ae7-a02ba8021991	en	{"title": "bet_user_5_fixture_1529023_goals_6_0", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
140	d5397a79-b717-4270-a72d-53cfff487945	en	{"title": "bet_user_5_fixture_1529023_goals_0_0", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
141	eca1b97e-4691-48ff-bbcb-d9dda6d7df34	en	{"title": "bet_user_5_fixture_1529023_goals_1_1", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
142	83de16c0-b51f-44f9-a8db-ed97ac535d4e	en	{"title": "bet_user_5_fixture_1529023_goals_2_1", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
143	ec68f90e-1a52-4f86-90ff-768d4a7c9a38	en	{"title": "bet_user_5_fixture_1529023_goals_3_0", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
144	3cfee252-308e-465b-bc46-d26875f90627	en	{"title": "bet_user_5_fixture_1529023_goals_4_0", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
145	b36c0c3a-c8b3-49ad-b2f0-fe618e33e31f	en	{"title": "bet_user_5_fixture_1529023_goals_5_0", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
146	6f2557d8-7d36-48e5-baad-87ce11bbf609	en	{"title": "bet_user_5_fixture_1529023_goals_6_0", "bet_type": "goals"}	draft	f	2026-08-28 16:29:02.013422+00
147	5f70bf1a-9a34-4334-a40d-d9f82e0d7abd	en	{"title": "Menu name", "description": "Readable administrative name for this menu."}	draft	f	2026-08-28 16:29:02.013422+00
148	6eb79fcf-f1b4-468c-89f3-3b6b437d9ed0	en	{"title": "Menu description", "description": "Administrative description of the menu."}	draft	f	2026-08-28 16:29:02.013422+00
149	771b41b7-9373-4ea2-862d-5e452d8c67da	en	{"title": "Item title", "description": "Visible label for links and headings."}	draft	f	2026-08-28 16:29:02.013422+00
150	618aa226-545e-490a-aa5a-4d5b4dc0f389	en	{"title": "URL", "description": "Absolute or relative link target."}	draft	f	2026-08-28 16:29:02.013422+00
151	f4ec7f26-95e0-4c80-b957-535a8f955f7a	en	{"title": "Icon", "description": "Icon name used by compatible templates."}	draft	f	2026-08-28 16:29:02.013422+00
152	ff959899-1251-4b1d-81c2-8563f24ca150	en	{"title": "Content manager"}	draft	f	2026-08-28 16:29:02.013422+00
153	041cb374-f326-4c70-9d40-b0b113951ef3	en	{"title": "Admin dashboard"}	draft	f	2026-08-28 16:29:02.013422+00
154	b4d620ee-9c26-4bc8-9694-a2ca59db928f	en	{"title": "Menu manager"}	draft	f	2026-08-28 16:29:02.013422+00
155	f2d5f754-cbff-4e6c-a945-38d229f4346e	en	{"title": "Page manager"}	draft	f	2026-08-28 16:29:02.013422+00
156	842fc2e0-20f9-427e-aaa3-aaa245e473bc	en	{"title": "User account"}	draft	f	2026-08-28 16:29:02.013422+00
157	4e8b7b25-7777-4974-892f-a56fd33681d0	en	{"title": "Home page"}	draft	f	2026-08-28 16:29:02.013422+00
158	a871c1f4-5040-47b8-9f37-19f0e0988020	uk	{"title":"Менеджер контенту","phrases":{"name":"Назва","save":"Зберегти","field":"Поле","items":"Елементи","order":"Порядок","title":"Назва","cancel":"Скасувати","delete":"Видалити","fields":"Поля","hidden":"Приховане","search":"Пошук…","source":"Джерело","status":"Статус","unique":"Унікальне","actions":"Дії","content":"Контент","indexed":"Індексоване","move_up":"Перемістити вище","used_in":"Використовується в","has_slug":"Елементи мають slug","inactive":"неактивний","multiple":"Кілька значень","new_item":"Новий елемент","no_items":"Елементів контенту не знайдено.","no_owner":"Без власника","readonly":"Лише читання","required":"Обов’язкове","edit_item":"Редагувати елемент","move_down":"Перемістити нижче","no_fields":"Полів не знайдено.","root_only":"Потрібен доступ Root.","edit_field":"Редагувати поле","field_type":"Тип поля","item_count":"{count} елементів","item_saved":"Елемент збережено.","no_manager":"Без менеджера","open_items":"Відкрити елементи","overridden":"Змінено вручну","save_field":"Зберегти поле","type_count":"{count} типів контенту","delete_item":"Видалити елемент контенту","description":"Опис","field_count":"{count} полів","field_saved":"Поле збережено.","fields_help":"Додавайте наявні поля, створюйте нові та змінюйте їхній порядок.","system_name":"Системна назва","title_field":"Поле назви","types_count":"Типи контенту","attach_field":"Додати наявне поле","content_type":"Тип контенту","create_field":"Створити поле","detach_field":"Прибрати зі структури","field_unused":"Не використовується","invalid_item":"Некоректний елемент контенту.","item_deleted":"Елемент видалено.","manage_items":"Пошук, створення, редагування та видалення елементів контенту.","owner_plugin":"Плагін-власник","save_manager":"Зберегти менеджер","translatable":"Перекладне","back_to_items":"Назад до елементів","content_types":"Типи контенту","default_value":"Типове значення","field_deleted":"Поле видалено.","from_manifest":"З manifest","manage_fields":"Поля","manager_reset":"Менеджер із manifest відновлений.","manager_saved":"Менеджер типу контенту збережений.","search_fields":"Пошук полів…","search_weight":"Вага пошуку","stored_values":"Є значення","summary_field":"Поле короткого опису","back_to_fields":"Назад до полів","edit_item_help":"Відредагуйте поля контенту та збережіть зміни.","edit_structure":"Редагувати структуру","existing_field":"Наявне поле","field_attached":"Поле додано до структури.","field_detached":"Поле прибрано зі структури, а його значення для цього типу контенту видалено.","field_settings":"Налаштування поля","item_not_found":"Елемент контенту не знайдено.","manage_schemas":"Керуйте схемами та елементами контенту.","manual_default":"Створено вручну","no_parent_type":"Без батьківського типу","saving_manager":"Збереження…","type_has_items":"Спочатку видаліть усі елементи цього типу.","current_manager":"Поточний менеджер","default_manager":"Типовий менеджер","field_not_found":"Поле не знайдено.","field_used_lock":"Перед видаленням приберіть це поле з усіх типів контенту.","invalid_manager":"Некоректний плагін-менеджер.","no_content_types":"Типів контенту не знайдено.","no_stored_values":"Немає значень","type_name_exists":"Тип контенту з такою системною назвою вже існує.","back_to_structure":"Назад до структури","edit_content_type":"Редагувати тип контенту","edit_global_field":"Редагувати глобальне поле","field_definitions":"Поля","field_editor_help":"Створіть глобальне визначення поля та налаштуйте його перше використання в цьому типі контенту.","field_name_exists":"Поле з такою системною назвою вже існує.","field_usage_count":"Використовується у {count} типах контенту.","field_values_lock":"Поле містить збережені значення, тому його не можна видалити.","invalid_direction":"Некоректний напрямок.","invalid_type_data":"Потрібні системна назва та звичайна назва. Використовуйте малі латинські літери, цифри й підкреслення.","manager_not_found":"Плагін-менеджер не знайдений.","save_content_type":"Зберегти тип контенту","type_has_children":"Цей тип контенту має дочірні типи.","content_type_saved":"Тип контенту збережено.","delete_item_failed":"Не вдалося видалити елемент контенту.","field_options_help":"Для полів select: масив об’єктів із ключами title та value.","field_options_json":"Опції select (JSON)","field_still_in_use":"Поле входить до структури типу контенту, тому його не можна видалити.","global_field_saved":"Глобальне поле збережено.","invalid_field_data":"Потрібні системна назва та звичайна назва. Використовуйте малі латинські літери, цифри й підкреслення.","invalid_field_type":"Некоректний тип поля.","confirm_delete_item":"Видалити \\"{title}\\"? Цю дію неможливо скасувати.","create_content_type":"Створити тип контенту","delete_content_type":"Видалити тип контенту","delete_field_failed":"Не вдалося видалити поле.","field_parameter_max":"Максимальне значення","field_parameter_min":"Мінімальне значення","invalid_parent_type":"Некоректний батьківський тип контенту.","manager_save_failed":"Не вдалося зберегти менеджер типу контенту.","parent_content_type":"Батьківський тип контенту","confirm_delete_field":"Видалити це поле повністю? Цю дію неможливо скасувати.","confirm_detach_field":"Прибрати це поле зі структури? Його значення в елементах цього типу контенту буде остаточно видалено.","content_type_deleted":"Тип контенту видалено.","field_parameter_rows":"Видимі рядки","field_parameter_step":"Крок","invalid_manager_mode":"Некоректний режим зміни менеджера.","manage_type_managers":"Менеджери типів","name_and_description":"Назва й опис","search_content_types":"Пошук типів контенту…","back_to_content_types":"Назад до типів контенту","content_type_managers":"Менеджери типів контенту","delete_field_globally":"Видалити поле повністю","field_type_parameters":"Параметри типу","global_field_settings":"Глобальні параметри поля","invalid_field_options":"Опції поля мають бути коректним JSON-масивом.","select_existing_field":"Оберіть поле…","structure_editor_help":"Відредагуйте тип контенту та складіть його структуру з полів.","content_type_not_found":"Тип контенту не знайдено.","field_already_attached":"Це поле вже є у структурі.","field_definitions_help":"Переглядайте й редагуйте глобальні визначення полів або видаляйте поля, які більше не використовуються.","field_not_in_structure":"Цього поля немає у поточній структурі.","field_parameter_latest":"Найпізніше значення","field_parameter_options":"Опції (JSON)","invalid_field_parameter":"Некоректне значення параметра {parameter}.","restore_default_manager":"Відновити значення з manifest","field_identity_root_only":"Лише Root може перейменувати або змінити тип поля, спільного з іншим менеджером.","field_parameter_earliest":"Найраніше значення","field_parameter_max_help":"Найбільше дозволене числове значення.","field_parameter_min_help":"Найменше дозволене числове значення.","field_parameter_required":"Параметр {parameter} є обов’язковим.","global_field_editor_help":"Ці параметри визначають поле глобально й впливають на всі типи контенту, де воно використовується.","field_attachment_settings":"Параметри використання","field_identity_has_values":"Не можна змінити системну назву або тип поля, яке вже містить значення.","field_parameter_rows_help":"Початкова видима висота багаторядкового редактора.","field_parameter_step_help":"Дозволений крок між числовими значеннями.","content_type_access_denied":"Цим типом контенту керує інший плагін.","content_type_managers_help":"Доступне лише Root призначення адміністративного плагіна, відповідального за кожен тип контенту.","delete_content_type_failed":"Не вдалося видалити тип контенту.","field_parameter_max_length":"Максимальна довжина","field_parameter_media_root":"Корінь медіа","field_parameter_min_length":"Мінімальна довжина","structure_operation_failed":"Не вдалося оновити структуру.","autocomplete_source_invalid":"Поля-джерела автодоповнення мають бути індексованими текстовими полями.","confirm_delete_content_type":"Видалити \\"{title}\\"? Цю дію неможливо скасувати.","field_parameter_latest_help":"Найпізніша дозволена дата або часткова дата у форматі поля.","field_parameter_placeholder":"Підказка","field_type_requires_indexed":"Цей тип поля вимагає індексації.","declarative_structure_notice":"Цю структуру оголошено плагіном. Під час наступного оновлення плагіна ручні зміни можуть бути відновлені з install/structures.json.","field_attachment_editor_help":"Ці параметри діють лише для цього поля в поточному типі контенту. Залиште назву порожньою, щоб використовувати глобальну.","field_parameter_media_accept":"Дозволені медіа","field_parameter_options_help":"Масив об’єктів із ключами title або label та value.","invalid_field_parameter_json":"Параметр {parameter} має містити коректний JSON.","field_parameter_content_types":"Дозволені типи контенту","field_parameter_earliest_help":"Найраніша дозволена дата або часткова дата у форматі поля.","field_parameter_source_fields":"Поля-джерела підказок","multiple_boolean_not_supported":"Булеве поле не може містити кілька значень.","field_parameter_max_length_help":"Максимально дозволена кількість символів.","field_parameter_media_root_help":"Необов’язкова підтeка Media, яка буде логічним коренем вибору.","field_parameter_min_length_help":"Мінімально дозволена кількість символів.","confirm_delete_field_with_values":"Видалити \\"{title}\\" разом з усіма збереженими значеннями, що залишилися? Цю дію неможливо скасувати.","field_parameter_placeholder_help":"Текст, що відображається, поки поле порожнє.","field_parameter_media_accept_help":"Необов’язкові MIME/розширення через кому для фільтра вибору, наприклад image/*,pdf.","field_parameter_content_types_help":"Типи контенту, елементи яких можна вибирати в цьому полі.","field_parameter_source_fields_help":"Індексовані текстові поля, наявні значення яких пропонуються як підказки. Залиште порожнім, щоб використовувати саме це поле.","field_parameter_components":"Компоненти","field_parameter_components_help":"Визначте вбудовані компоненти значення, які належать лише цьому складеному полю.","compound_components":"Компоненти","compound_add_component":"Додати компонент","compound_component_name":"Системна назва компонента","compound_components_invalid":"Некоректне визначення компонентів складеного поля.","remove":"Видалити","compound_component_name_duplicate":"Системні назви компонентів не можуть повторюватися.","compound_select_options_invalid":"Опції мають бути коректним JSON-масивом."},"handlers":{"view":{"title":"Перегляд контенту","actions":{"getItem":{"title":"Отримати елемент контенту"},"getType":{"title":"Отримати структуру типу контенту"},"listTypes":{"title":"Список типів контенту"},"searchItems":{"title":"Пошук елементів контенту"}}},"manage":{"title":"Керування контентом","actions":{"config":{"title":"Налаштування контенту"},"itemEdit":{"title":"Редагувати елемент контенту"},"itemList":{"title":"Список елементів контенту"},"itemSave":{"title":"Зберегти елемент контенту"},"typeEdit":{"title":"Редагувати тип контенту"},"typeList":{"title":"Список типів контенту"},"typeSave":{"title":"Зберегти тип контенту"},"fieldEdit":{"title":"Редагувати поле контенту"},"fieldList":{"title":"Керувати глобальними полями контенту"},"fieldMove":{"title":"Змінити порядок полів"},"fieldSave":{"title":"Зберегти поле контенту"},"itemDelete":{"title":"Видалити елемент контенту"},"typeDelete":{"title":"Видалити тип контенту"},"fieldAttach":{"title":"Додати поле до структури"},"fieldDelete":{"title":"Видалити поле контенту"},"fieldDetach":{"title":"Прибрати поле зі структури"},"globalFieldEdit":{"title":"Редагувати глобальне поле контенту"},"globalFieldSave":{"title":"Зберегти глобальне поле контенту"},"typeManagerList":{"title":"Керувати менеджерами типів контенту"},"typeManagerUpdate":{"title":"Оновити менеджер типу контенту"}}},"content":{"title":"Редагування контенту","actions":{"createItem":{"title":"Створити елемент контенту"},"deleteItem":{"title":"Видалити елемент контенту"},"updateItem":{"title":"Оновити елемент контенту"}}},"structure":{"title":"Редагування структури контенту","actions":{"updateType":{"title":"Оновити тип контенту"},"attachField":{"title":"Додати поле до структури"},"detachField":{"title":"Прибрати поле зі структури"},"reorderFields":{"title":"Змінити порядок полів"}}}},"description":"Створення та керування типами контенту й елементами контенту."}	draft	f	2026-08-28 16:29:02.013422+00
159	d875f2f6-b69e-4be9-a232-f42f71a3990e	uk	{"title": "Статичний контент", "description": "Багаторазовий блок статичного контенту."}	draft	f	2026-08-30 03:54:35.799895+00
160	44bf3a4d-3b78-46d1-a7d5-44b711705c1a	en	{"title": "Content", "description": "Formatted content of the static block."}	draft	f	2026-08-28 16:29:02.013422+00
161	44bf3a4d-3b78-46d1-a7d5-44b711705c1a	uk	{"title": "Контент", "description": "Форматований вміст статичного блока."}	draft	f	2026-08-28 16:29:02.013422+00
162	08a10f8a-cf9f-4875-9fe1-f49b8172505d	en	{"title": "Published at", "description": "Date and time when the article was published."}	draft	f	2026-08-28 16:29:02.013422+00
163	08a10f8a-cf9f-4875-9fe1-f49b8172505d	uk	{"title": "Дата публікації", "description": "Дата й час публікації статті."}	draft	f	2026-08-28 16:29:02.013422+00
164	298011f1-17db-467c-8c57-e8ca67f63915	en	{"title": "Updated at", "description": "Date and time of the latest article update."}	draft	f	2026-08-28 16:29:02.013422+00
165	298011f1-17db-467c-8c57-e8ca67f63915	uk	{"title": "Дата оновлення", "description": "Дата й час останнього оновлення статті."}	draft	f	2026-08-28 16:29:02.013422+00
166	c30cc95c-776a-44dc-8d65-bf0e110f6dcb	en	{"title": "Summary", "description": "Short summary displayed in article lists and previews."}	draft	f	2026-08-28 16:29:02.013422+00
167	c30cc95c-776a-44dc-8d65-bf0e110f6dcb	uk	{"title": "Короткий опис", "description": "Короткий опис для списків і попереднього перегляду статей."}	draft	f	2026-08-28 16:29:02.013422+00
168	90c6a493-1ad9-4bac-a1fa-27800fafb10c	en	{"title": "Article body", "description": "Formatted article content."}	draft	f	2026-08-28 16:29:02.013422+00
169	90c6a493-1ad9-4bac-a1fa-27800fafb10c	uk	{"title": "Текст статті", "description": "Форматований вміст статті."}	draft	f	2026-08-28 16:29:02.013422+00
170	3564f84d-fdac-4250-acb0-b91bcedd5c1c	uk	{"title": "Навігація", "phrases": {"url": "URL", "edit": "Редагувати", "save": "Зберегти", "menus": "Меню", "pages": "Сторінки", "title": "Назва", "users": "Користувачі", "cancel": "Скасувати", "delete": "Видалити", "logout": "Вийти", "actions": "Дії", "content": "Контент", "add_item": "Додати пункт", "menu_key": "Ключ меню", "no_menus": "Меню не знайдено.", "settings": "Налаштування", "drag_item": "Перетягнути пункт меню", "menu_name": "Назва меню", "menu_count": "{count} меню", "menu_items": "Пункти меню", "navigation": "Навігація", "add_subitem": "Додати підпункт", "create_menu": "Створити меню", "delete_item": "Видалити пункт", "manage_menus": "Створення та редагування навігаційних меню.", "menu_deleted": "Меню видалено.", "translations": "Переклади", "back_to_menus": "Назад до меню", "delete_failed": "Не вдалося видалити меню.", "icon_optional": "Іконка (необов’язково)", "menu_settings": "Параметри меню", "edit_menu_help": "Редагуйте параметри меню та порядок його пунктів.", "menu_not_found": "Меню не знайдено.", "menu_items_help": "Перетягуйте пункти, щоб змінити порядок або рівень вкладеності.", "menu_description": "Опис меню", "menu_key_warning": "Зміна цього ключа може порушити recipes, налаштування плагінів або прив’язки теми. Змінюйте його лише якщо розумієте наслідки.", "menu_key_required": "Ключ меню є обов’язковим.", "menu_name_required": "Назва меню є обов’язковою.", "confirm_delete_item": "Ви впевнені, що хочете видалити цей пункт? Цю дію неможливо скасувати.", "confirm_delete_menu": "Видалити «{title}»? Цю дію неможливо скасувати."}, "handlers": {"view": {"title": "Виведення навігації", "actions": {"show": {"title": "Показати меню"}}, "instance_params": {"menu_id": {"title": "Меню для виведення", "description": "Оберіть меню, яке потрібно показати."}, "template": {"title": "Шаблон навігації", "options": {"footer": "Меню в нижній секції", "topnav": "Верхня панель навігації", "sidebar": "Бічне меню"}, "description": "Оберіть шаблон для виведення навігаційного меню."}}}, "manage": {"title": "Керування навігацією", "actions": {"edit": {"title": "Редагувати меню"}, "list": {"title": "Список меню"}, "save": {"title": "Зберегти меню"}, "create": {"title": "Створити меню"}, "delete": {"title": "Видалити меню"}}}}, "description": "Створення навігаційних меню та їх виведення у макетах сторінок."}	draft	f	2026-08-28 16:29:02.013422+00
171	75c059a3-d9e8-4572-8d18-5f4823266a99	uk	{"title": "Меню", "description": "Навігаційне меню."}	draft	f	2026-08-28 16:29:02.013422+00
172	5f70bf1a-9a34-4334-a40d-d9f82e0d7abd	uk	{"title": "Назва меню", "description": "Зрозуміла службова назва меню."}	draft	f	2026-08-28 16:29:02.013422+00
173	6eb79fcf-f1b4-468c-89f3-3b6b437d9ed0	uk	{"title": "Опис меню", "description": "Службовий опис меню."}	draft	f	2026-08-28 16:29:02.013422+00
174	4ff2a6c5-6b64-4344-a33a-0f14e8b9bc55	uk	{"title": "Пункт меню", "description": "Елемент навігаційного меню."}	draft	f	2026-08-28 16:29:02.013422+00
175	8217bf2c-21de-437d-8efd-2de368e79d4f	en	{"title": "Item type", "options": [{"title": "Link", "value": "link"}, {"title": "Heading", "value": "heading"}, {"title": "Divider", "value": "divider"}], "description": "Defines how this menu item is rendered."}	draft	f	2026-08-28 16:29:02.013422+00
176	8217bf2c-21de-437d-8efd-2de368e79d4f	uk	{"title": "Тип пункту", "options": [{"title": "Посилання", "value": "link"}, {"title": "Заголовок", "value": "heading"}, {"title": "Роздільник", "value": "divider"}], "description": "Визначає спосіб виведення пункту меню."}	draft	f	2026-08-28 16:29:02.013422+00
177	771b41b7-9373-4ea2-862d-5e452d8c67da	uk	{"title": "Назва пункту", "description": "Видимий текст посилання або заголовка."}	draft	f	2026-08-28 16:29:02.013422+00
178	618aa226-545e-490a-aa5a-4d5b4dc0f389	uk	{"title": "URL", "description": "Абсолютна або відносна адреса посилання."}	draft	f	2026-08-28 16:29:02.013422+00
179	f4ec7f26-95e0-4c80-b957-535a8f955f7a	uk	{"title": "Іконка", "description": "Назва іконки для сумісних шаблонів."}	draft	f	2026-08-28 16:29:02.013422+00
180	eca96222-7643-4545-bd1a-b02e959fdb6c	en	{"title": "Display order", "description": "Position of the item within its menu level."}	draft	f	2026-08-28 16:29:02.013422+00
181	eca96222-7643-4545-bd1a-b02e959fdb6c	uk	{"title": "Порядок виведення", "description": "Позиція пункту на відповідному рівні меню."}	draft	f	2026-08-28 16:29:02.013422+00
182	18bfd168-a278-42f9-8468-92f0dec931bf	uk	{"title": "Менеджер сторінок", "phrases": {"edit": "Редагувати", "head": "Секція head", "name": "Назва", "save": "Зберегти", "slug": "Slug", "pages": "Сторінки", "retry": "Спробувати ще раз", "title": "Назва", "cancel": "Скасувати", "delete": "Видалити", "domain": "Домен", "footer": "Нижня секція", "layout": "Макет", "recipe": "Recipe", "actions": "Дії", "content": "Контент", "recipes": "Recipes", "add_page": "Додати сторінку", "no_pages": "Сторінок не знайдено.", "edit_page": "Редагувати сторінку", "save_page": "Зберегти сторінку", "no_recipes": "Recipes не знайдено.", "page_saved": "Сторінку збережено.", "recipe_key": "Ключ recipe", "create_page": "Створити сторінку", "delete_page": "Видалити сторінку", "description": "Опис", "edit_recipe": "Редагувати recipe", "move_plugin": "Перемістити плагін", "parent_page": "Батьківська сторінка", "top_section": "Верхня секція", "new_page_for": "Нова сторінка для", "page_recipes": "Recipes сторінок", "payload_json": "Payload JSON", "back_to_pages": "Назад до сторінок", "layout_loaded": "Макет завантажено", "loading_pages": "Завантаження сторінок…", "remove_plugin": "Прибрати плагін", "select_domain": "Оберіть домен…", "select_recipe": "Оберіть recipe…", "layout_preview": "Макет сторінки", "loading_layout": "Завантаження макета сторінки…", "manage_domains": "Керувати доменами", "no_parent_page": "Без батьківської сторінки", "page_count_few": "{count} сторінки", "page_count_one": "{count} сторінка", "select_handler": "Оберіть handler", "page_count_many": "{count} сторінок", "plugin_settings": "Налаштування плагіна", "unknown_wrapper": "Цю зону не оголошено в макеті.", "page_count_other": "{count} сторінки", "available_plugins": "Доступні плагіни", "drag_plugins_help": "Перетягніть плагін у будь-яку доступну зону сторінки.", "drag_plugins_here": "Перетягніть плагіни сюди", "load_pages_failed": "Не вдалося завантажити сторінки.", "parent_page_cycle": "Сторінку не можна розмістити всередині неї самої або однієї з її дочірніх сторінок.", "unplaced_wrappers": "Нерозміщені wrapper-и", "create_from_recipe": "З recipe", "delete_page_failed": "Не вдалося видалити сторінку.", "domain_unavailable": "Домен недоступний.", "layout_load_failed": "Не вдалося завантажити макет сторінки.", "no_domain_selected": "Домен не обрано", "recipe_page_prefix": "Префікс URL: {prefix}", "delete_page_confirm": "Видалити «{title}»? Цю дію неможливо скасувати.", "invalid_parent_page": "Обрана батьківська сторінка недійсна.", "layout_preview_help": "Розташування зон нижче відповідає структурі обраного макета.", "delete_recipe_confirm": "Видалити цей recipe?", "recipe_no_page_prefix": "Без префікса URL", "create_from_recipe_for": "Нова сторінка з recipe для", "unplaced_wrappers_help": "Ці wrapper-и містять дані, але не мають відповідної зони у preview макета.", "loading_plugin_settings": "Завантаження налаштувань плагіна…", "page_recipes_description": "Повторно використовувані схеми сторінок: layout, стандартні екземпляри плагінів і навігація.", "plugin_settings_load_failed": "Не вдалося завантажити налаштування плагіна. Спробуйте ще раз.", "select_domain_to_view_pages": "Оберіть домен, щоб переглянути його сторінки"}, "handlers": {"manage": {"title": "Керування сторінками", "actions": {"edit": {"title": "Редагувати сторінку"}, "list": {"title": "Список сторінок"}, "save": {"title": "Зберегти сторінку"}, "config": {"title": "Налаштування сторінок"}, "delete": {"title": "Видалити сторінку"}, "recipes": {"title": "Recipes сторінок"}, "settings": {"title": "Налаштування менеджера сторінок"}, "createPage": {"title": "Створити сторінку"}, "deletePage": {"title": "Delete page"}, "recipeSave": {"title": "Зберегти recipe сторінки"}, "domainPages": {"title": "Load domain pages"}, "recipeDelete": {"title": "Видалити recipe сторінки"}, "pageLayoutData": {"title": "Завантажити макет сторінки"}, "pluginContextForm": {"title": "Налаштування екземпляра плагіна"}, "createPageFromRecipe": {"title": "Створити сторінку з recipe"}}}}, "settings": {"test": {"title": "Тестове налаштування", "description": "Тимчасове налаштування для розробки."}}, "description": "Створення сторінок, вибір макетів і розміщення плагінів у секціях сторінки."}	draft	f	2026-08-28 16:29:02.013422+00
183	c19033b7-c7b4-47c3-a3c2-56dc10d2b36f	uk	{"title":"Менеджер тем","description":"Встановлення, оновлення та видалення тем.","handlers":{"manage":{"title":"Керування","actions":{"overview":{"title":"Теми"},"lifecycle":{"title":"Встановити, оновити або видалити тему"}}}},"phrases":{"themes":"Теми","themes_help":"Теми, знайдені у теці themes, та встановлені записи тем.","theme":"Тема","version":"Версія","status":"Статус","used_on":"Використовується","actions":"Дії","install":"Встановити","update":"Оновити","uninstall":"Видалити","installed":"Встановлено","not_installed":"Не встановлено","files_missing":"Файли відсутні","confirm_uninstall":"Видалити цю тему з системи?","operation_failed":"Операцію з темою не виконано."}}	draft	f	2026-08-28 16:29:02.013422+00
184	8c05bbc4-1aad-43f5-8592-923c25976eb2	uk	{"title": "Профіль користувача", "phrases": {"save": "Зберегти", "email": "Електронна адреса", "guest": "Гість", "login": "Увійти", "logout": "Вийти", "connect": "Підключити", "profile": "Профіль", "replace": "Замінити", "website": "Вебсайт", "welcome": "Вітаємо,", "password": "Пароль", "register": "Зареєструватися", "username": "Ім’я користувача", "verified": "Підтверджено", "connected": "Підключено", "configured": "Налаштовано", "disconnect": "Відключити", "email_same": "Нова електронна адреса збігається з поточною.", "credentials": "Облікові дані", "email_taken": "Ця електронна адреса вже використовується.", "preferences": "Налаштування", "change_email": "Змінити email", "display_name": "Відображуване ім’я", "new_password": "Новий пароль", "not_verified": "Не підтверджено", "save_changes": "Зберегти зміни", "set_password": "Задати пароль", "email_invalid": "Введіть коректну електронну адресу.", "not_connected": "Не підключено", "password_help": "Використовуйте пароль як один із доступних способів входу до цього акаунта.", "pending_email": "Очікує підтвердження", "username_help": "Літери, цифри, крапки, підкреслення та дефіси.", "email_required": "Введіть електронну адресу.", "not_configured": "Не налаштовано", "password_short": "Пароль має містити щонайменше 8 символів.", "username_taken": "Це ім’я користувача вже використовується.", "change_password": "Змінити пароль", "profile_updated": "Ваш профіль оновлено.", "remove_password": "Вилучити пароль", "repeat_password": "Повторіть новий пароль", "sign_in_methods": "Способи входу", "current_password": "Поточний пароль", "last_auth_method": "Не можна вилучити останній спосіб входу.", "password_changed": "Пароль оновлено.", "password_removed": "Вхід за паролем вимкнено.", "username_changed": "Ім’я користувача оновлено.", "username_invalid": "Некоректний формат імені користувача.", "credentials_intro": "Керуйте обліковими даними та способами входу до свого акаунта.", "email_change_help": "Поточна адреса залишається чинною, доки нову адресу не буде підтверджено.", "email_change_sent": "На нову електронну адресу надіслано посилання для підтвердження.", "no_public_profile": "Цей користувач не має публічного профілю.", "password_mismatch": "Паролі не збігаються.", "password_required": "Введіть пароль.", "username_required": "Введіть ім’я користувача.", "disconnect_confirm": "Відключити цей спосіб входу?", "pending_email_help": "На цю адресу надіслано посилання для підтвердження.", "remove_password_help": "Вхід за паролем буде вимкнено. Має залишитися інший спосіб входу.", "sign_in_methods_help": "Підключайте, замінюйте або відключайте зовнішніх провайдерів автентифікації.", "provider_disconnected": "Провайдера автентифікації відключено.", "credentials_load_failed": "Не вдалося завантажити облікові дані.", "provider_used_elsewhere": "Цей Google-акаунт уже підключено до іншого користувача.", "current_password_invalid": "Поточний пароль неправильний.", "credentials_action_failed": "Не вдалося виконати запитану зміну акаунта.", "provider_already_connected": "Google-акаунт уже підключено. Скористайтеся заміною, щоб змінити його."}, "handlers": {"view": {"title": "Виведення профілю", "actions": {"statusbar": {"title": "Панель користувача"}, "profile_page": {"title": "Сторінка профілю"}}}, "manage": {"title": "Керування профілями", "actions": {"config": {"title": "Налаштування профілю"}}}, "account": {"title": "Акаунт", "actions": {"credentials": {"title": "Облікові дані"}, "change_email": {"title": "Змінити email"}, "change_password": {"title": "Змінити пароль"}, "change_username": {"title": "Змінити ім’я користувача"}, "remove_password": {"title": "Вилучити пароль"}, "disconnect_provider": {"title": "Відключити провайдера"}}}}, "settings": {"profile_page_enabled": {"title": "Сторінку профілю увімкнено", "description": "Дозволяє користувачам мати публічну сторінку профілю на цьому домені."}}, "description": "Керування даними профілю, обліковими даними та параметрами акаунта."}	draft	f	2026-08-28 16:29:02.013422+00
185	5c2f8d58-b584-44e4-b71a-97fecc2512ea	uk	{"title": "Профіль", "description": "Публічні дані профілю користувача."}	draft	f	2026-08-28 16:29:02.013422+00
186	ec836f0c-9db5-418a-92e3-395ad5c15dcd	uk	{"title": "Відображуване ім’я", "description": "Публічне ім’я користувача."}	draft	f	2026-08-28 16:29:02.013422+00
187	1dec29c1-3552-4ee6-ab7d-21f0677205ec	uk	{"title": "Вебсайт", "description": "Публічна адреса вебсайту."}	draft	f	2026-08-28 16:29:02.013422+00
188	7c294eb7-4faf-4e7b-8d6e-cd85f04285c5	uk	{"title":"Перегляд статичного контенту","description":"Виведення окремого елемента статичного контенту на сайті.","handlers":{"view":{"title":"Виведення статичного контенту","actions":{"view":{"title":"Переглянути статичний контент"}},"instance_params":{"item_id":{"title":"Елемент статичного контенту","description":"Елемент статичного контенту для виведення."}}},"manage":{"title":"Керування статичним контентом","actions":{"config":{"title":"Налаштування статичного контенту"}}}},"phrases":{"incorrect_viewer_call":"Плагін перегляду статичного контенту викликано з некоректними параметрами.","no_content":"Статичний контент відсутній."}}	draft	f	2026-08-28 16:29:02.013422+00
189	5b786a30-fc2c-43f3-8551-c4c89a1fd425	en	{"title": "About Kami"}	draft	f	2026-08-28 16:29:02.013422+00
190	0d8b9ff4-a9f5-4d9f-988c-077334c93d15	en	{"title": "Internal title", "description": "Used only to identify this content item in the administration interface."}	draft	f	2026-08-28 16:29:02.013422+00
191	0d8b9ff4-a9f5-4d9f-988c-077334c93d15	uk	{"title": "Внутрішня назва", "description": "Використовується лише для пошуку та розпізнавання цього матеріалу в адмінці."}	draft	f	2026-08-28 16:29:02.013422+00
192	1241eab1-da3b-47bf-a237-587f7970e062	en	{"title": "Forms", "phrases": {"remove": "Remove", "submit": "Submit", "add_url": "Add URL", "move_up": "Move up", "add_value": "Add value", "move_down": "Move down", "browse_media": "Browse Media"}, "handlers": {"view": {"title": "View", "actions": {"entityOptions": {"title": "Load system entity options"}, "autocompleteOptions": {"title": "Load autocomplete suggestions"}}}}, "description": "Server-side form and field rendering with template fallback."}	draft	f	2026-08-28 16:29:02.013422+00
193	1241eab1-da3b-47bf-a237-587f7970e062	uk	{"title": "Форми", "phrases": {"remove": "Видалити", "submit": "Надіслати", "add_url": "Додати URL", "move_up": "Вище", "add_value": "Додати значення", "move_down": "Нижче", "browse_media": "Вибрати в медіа"}, "handlers": {"view": {"title": "View", "actions": {"entityOptions": {"title": "Load system entity options"}, "autocompleteOptions": {"title": "Load autocomplete suggestions"}}}}, "description": "Серверний рендеринг форм і полів із резервними шаблонами."}	draft	f	2026-08-28 16:29:02.013422+00
194	9bda5536-e965-4359-a6ad-fdee8814a0aa	en	{"title": "Related items", "description": ""}	draft	f	2026-08-28 16:29:02.013422+00
195	55c0f873-25ee-4094-9ea9-e95eb6b402b4	en	{"title": "User ID", "description": "Integer identifier of a user."}	published	t	2026-08-28 16:29:02.013422+00
196	55c0f873-25ee-4094-9ea9-e95eb6b402b4	uk	{"title": "ID користувача", "description": "Цілочисельний ідентифікатор користувача."}	published	f	2026-08-28 16:29:02.013422+00
197	f9bbbf08-b328-4019-9455-991eedc80528	en	{"title": "Time", "description": "Time of day without an associated calendar date."}	published	t	2026-08-28 16:29:02.013422+00
198	f9bbbf08-b328-4019-9455-991eedc80528	uk	{"title": "Час", "description": "Час доби без прив’язки до календарної дати."}	published	f	2026-08-28 16:29:02.013422+00
199	33b60373-705d-4b9c-892a-40fda0aa64dc	en	{"title": "Item template", "options": [{"title": "Default", "value": "default"}, {"title": "Container", "value": "container"}, {"title": "Card", "value": "card"}, {"title": "Compact", "value": "compact"}], "description": "Template used to render the static content item."}	draft	f	2026-08-28 16:29:02.013422+00
200	990d42d6-edac-4afe-aa60-9ce338d4163e	en	{"title": "Static content block", "description": "Wrapper for several content items."}	draft	f	2026-08-28 16:29:02.013422+00
201	98a8eb58-ea4e-4090-aed6-dadcc6be76d9	en	{"title": "Content items", "description": "Content items included in this block."}	draft	f	2026-08-28 16:29:02.013422+00
202	cffa0508-28a8-4377-93de-d094bceccce0	en	{"display_title": "Several content blocks in one grid"}	draft	f	2026-09-03 17:09:22.662858+00
203	2d545e1a-af53-4df0-8377-97234528c8b2	en	{"static_content_body": "<h1>Static page</h1><h2>Different templates for content blocks</h2><p>A content block can use any template provided by its plugin or overridden by the current theme. Changing the template changes how the content is presented without changing the content itself.</p>"}	draft	f	2026-09-04 10:40:25.053864+00
204	adddbefc-7afa-4f9b-b337-81a3434760c4	en	{"static_content_body": "<h1>Hello, Kami.</h1><h2>A clean foundation for whatever comes next.</h2><p>Your new site is up and running. Add content, shape the structure, and keep building from here.</p>"}	draft	f	2026-08-29 08:14:00.95826+00
205	53813421-b7aa-4454-b104-13988872b244	en	{"static_content_body": "<p>Nothing here requires a special page type. This page is simply assembled from reusable content blocks, a group, and a few templates.</p>"}	draft	f	2026-09-04 10:48:54.454954+00
206	dde69f0e-2a4a-4c6c-b01d-309a93f67c5a	en	{"title": "language switcher", "handlers": {"view": {"title": "View", "actions": {"view": {"title": "View"}}, "instance_params": {"template": {"title": "Language switcher template", "options": {"footer": "Flag links", "select": "Select", "simple": "Simple links"}, "description": "Select the template used to render the language switcher."}}}}, "description": "Plugin to display single static content item on front-end."}	draft	f	2026-08-28 16:29:02.013422+00
207	ea6d332b-27c1-46e5-a062-aa29fd9a759f	en	{"title": "Menu key", "description": "Stable technical identifier used by recipes, plugins, and theme bindings."}	draft	f	2026-08-28 16:29:02.013422+00
208	ea6d332b-27c1-46e5-a062-aa29fd9a759f	uk	{"title": "Ключ меню", "description": "Стабільний технічний ідентифікатор, який використовують recipes, плагіни та прив’язки теми."}	draft	f	2026-08-28 16:29:02.013422+00
209	b7035ae7-3fc5-4da0-a099-f7a809ea5941	en	{"item_title": "Credentials"}	draft	f	2026-09-03 16:51:57.179319+00
210	13c52e01-3894-4a06-8b5f-df2d4f55f966	en	{"item_title": "Admin dashboard"}	draft	f	2026-09-03 16:51:57.180814+00
211	a0455cb5-a499-493e-bb27-d253435beaca	en	{"item_title": "Pages"}	draft	f	2026-09-03 05:25:28.607371+00
212	be358f3f-97bc-4cb8-aed2-9694da93719c	en	{"title": "Plugin Manager", "phrases": {"save": "Save", "setup": "Setup", "active": "Active", "author": "Author", "cancel": "Cancel", "enable": "Enable", "manage": "Manage", "status": "Status", "update": "Update", "actions": "Actions", "disable": "Disable", "domains": "Domains", "install": "Install", "plugins": "Plugins", "version": "Version", "inactive": "Inactive", "settings": "Settings", "uninstall": "Uninstall", "dependencies": "Dependencies", "not_installed": "Not installed", "plugin_manager": "Plugin manager", "back_to_plugins": "Back to plugins", "available_plugins": "Available plugins", "confirm_uninstall": "Are you sure you want to uninstall this plugin?", "installed_plugins": "Installed plugins", "missing_dependencies": "Required dependencies are missing."}, "handlers": {"manage": {"title": "Manage", "actions": {"list": {"title": "Plugins"}, "setup": {"title": "Setup plugin"}, "plugin": {"title": "Plugin settings"}, "lifecycle": {"title": "Plugin lifecycle action"}, "applySetup": {"title": "Apply setup plan"}, "resolveSetup": {"title": "Resolve setup plan"}, "pluginActivation": {"title": "Plugin domain activation"}, "pluginSettingsSave": {"title": "Save plugin settings"}, "pluginDomainSettings": {"title": "Load plugin domain settings"}}}}, "description": "Install, update, enable, configure, and remove plugins."}	draft	f	2026-08-28 16:29:02.013422+00
213	be358f3f-97bc-4cb8-aed2-9694da93719c	uk	{"title": "Менеджер плагінів", "phrases": {"save": "Зберегти", "setup": "Налаштувати", "active": "Активний", "author": "Автор", "cancel": "Скасувати", "enable": "Увімкнути", "manage": "Керувати", "status": "Статус", "update": "Оновити", "actions": "Дії", "disable": "Вимкнути", "domains": "Домени", "install": "Встановити", "plugins": "Плагіни", "version": "Версія", "inactive": "Неактивний", "settings": "Налаштування", "uninstall": "Видалити", "dependencies": "Залежності", "not_installed": "Не встановлено", "plugin_manager": "Менеджер плагінів", "back_to_plugins": "Назад до плагінів", "available_plugins": "Доступні плагіни", "confirm_uninstall": "Ви впевнені, що хочете видалити цей плагін?", "installed_plugins": "Встановлені плагіни", "missing_dependencies": "Не встановлено необхідні залежності."}, "handlers": {"manage": {"title": "Manage", "actions": {"list": {"title": "Plugins"}, "setup": {"title": "Setup plugin"}, "plugin": {"title": "Plugin settings"}, "lifecycle": {"title": "Plugin lifecycle action"}, "applySetup": {"title": "Apply setup plan"}, "resolveSetup": {"title": "Resolve setup plan"}, "pluginActivation": {"title": "Plugin domain activation"}, "pluginSettingsSave": {"title": "Save plugin settings"}, "pluginDomainSettings": {"title": "Load plugin domain settings"}}}}, "description": "Встановлення, оновлення, увімкнення, налаштування та видалення плагінів."}	draft	f	2026-08-28 16:29:02.013422+00
214	2a56244f-9f40-4ac6-b24e-311f21bdcfae	en	{"title": "Plugin manager"}	draft	f	2026-08-28 16:29:02.013422+00
215	3c6bf6e5-3100-4825-997a-817503bcd287	en	{"title": "Text processor", "description": "Provides a common API for external text processing providers."}	draft	f	2026-08-28 16:29:02.013422+00
216	6f2d882b-4846-42f1-b9f2-c057a1de1a8e	en	{"title": "Translation manager", "phrases": {"back": "Back", "load": "Load", "save": "Save", "stop": "Stop", "saved": "Translation saved.", "scope": "Scope", "delete": "Delete", "errors": "Errors", "source": "Source", "content": "Content", "context": "Context", "skipped": "Skipped items", "provider": "Provider", "translate": "Translate", "batch_done": "Done.", "new_phrase": "New phrase", "phrase_key": "Phrase key", "translated": "Translated fields", "all_missing": "All missing", "select_item": "Select item", "translation": "Translation", "current_page": "Current page", "instructions": "Instructions", "batch_running": "Translating...", "batch_stopped": "Stopped.", "delete_phrase": "Delete phrase from all languages", "batch_stopping": "Stopping...", "selected_items": "Selected items", "source_language": "Source language", "system_entities": "System entities", "target_language": "Target language", "dictionary_empty": "The system dictionary is empty.", "dictionary_saved": "System dictionary saved.", "system_dictionary": "System dictionary", "translate_missing": "Translate missing", "translation_ready": "Translation draft is ready.", "batch_select_items": "Select at least one item.", "translation_missing": "Translation is missing.", "translation_outdated": "Translation may be outdated.", "dictionary_key_exists": "This phrase key already exists.", "no_source_translation": "No exact source translation is available.", "batch_languages_differ": "Source and target languages must be different.", "dictionary_key_invalid": "Phrase keys must use lowercase Latin letters, digits and underscores.", "translation_updated_at": "Last updated:", "dictionary_source_required": "Source text is required for a new phrase."}, "handlers": {"manage": {"title": "Manage", "actions": {"overview": {"title": "Overview"}, "systemEdit": {"title": "Edit system translation"}, "systemList": {"title": "System entities"}, "systemSave": {"title": "Save system translation"}, "contentEdit": {"title": "Edit content translation"}, "contentList": {"title": "Content"}, "contentSave": {"title": "Save content translation"}, "batchTranslate": {"title": "Batch translate"}, "dictionaryEdit": {"title": "Edit system dictionary"}, "dictionarySave": {"title": "Save system dictionary"}, "systemTranslate": {"title": "Translate system entity"}, "contentTranslate": {"title": "Translate content"}, "dictionaryTranslate": {"title": "Translate system dictionary"}}}}, "description": "Manage system and content translations."}	draft	f	2026-09-04 20:10:42.424722+00
217	6f2d882b-4846-42f1-b9f2-c057a1de1a8e	uk	{"title": "Менеджер перекладів", "phrases": {"back": "Назад", "load": "Завантажити", "save": "Зберегти", "stop": "Зупинити", "saved": "Переклад збережено.", "scope": "Обсяг", "delete": "Видалити", "errors": "Помилки", "source": "Оригінал", "content": "Контент", "context": "Контекст", "skipped": "Пропущено елементів", "provider": "Провайдер", "translate": "Перекласти", "batch_done": "Готово.", "new_phrase": "Нова фраза", "phrase_key": "Ключ фрази", "translated": "Перекладено полів", "all_missing": "Усі відсутні", "select_item": "Вибрати елемент", "translation": "Переклад", "current_page": "Поточна сторінка", "instructions": "Інструкції", "batch_running": "Перекладаємо...", "batch_stopped": "Зупинено.", "delete_phrase": "Видалити фразу з усіх мов", "batch_stopping": "Зупиняємо...", "selected_items": "Вибрані елементи", "source_language": "Мова оригіналу", "system_entities": "Системні сутності", "target_language": "Мова перекладу", "dictionary_empty": "Системний словник порожній.", "dictionary_saved": "Системний словник збережено.", "system_dictionary": "Системний словник", "translate_missing": "Перекласти відсутні", "translation_ready": "Чернетка перекладу готова.", "batch_select_items": "Виберіть хоча б один елемент.", "translation_missing": "Переклад відсутній.", "translation_outdated": "Переклад може бути застарілим.", "dictionary_key_exists": "Такий ключ фрази вже існує.", "no_source_translation": "Точного перекладу для мови-джерела немає.", "batch_languages_differ": "Мова оригіналу й перекладу мають відрізнятися.", "dictionary_key_invalid": "Ключі фраз мають містити лише малі латинські літери, цифри та підкреслення.", "translation_updated_at": "Останнє оновлення:", "dictionary_source_required": "Для нової фрази потрібен текст мовою оригіналу."}, "handlers": {"manage": {"title": "Керування", "actions": {"overview": {"title": "Огляд"}, "systemEdit": {"title": "Редагування системного перекладу"}, "systemList": {"title": "Системні сутності"}, "systemSave": {"title": "Зберегти системний переклад"}, "contentEdit": {"title": "Редагування перекладу контенту"}, "contentList": {"title": "Контент"}, "contentSave": {"title": "Зберегти переклад контенту"}, "batchTranslate": {"title": "Пакетний переклад"}, "dictionaryEdit": {"title": "Редагування системного словника"}, "dictionarySave": {"title": "Зберегти системний словник"}, "systemTranslate": {"title": "Перекласти системну сутність"}, "contentTranslate": {"title": "Перекласти контент"}, "dictionaryTranslate": {"title": "Перекласти системний словник"}}}}, "description": "Керування перекладами системних сутностей і контенту."}	draft	f	2026-09-04 20:10:42.425346+00
218	6c74d3b1-7bf2-4186-a592-c41663bf7fdd	uk	{"title": "Переклади"}	draft	f	2026-08-28 16:29:02.013422+00
219	6c74d3b1-7bf2-4186-a592-c41663bf7fdd	en	{"title": "Translations"}	draft	f	2026-08-28 16:29:02.013422+00
220	f1d7852c-bdea-4800-ad5e-d136b0b5a712	en	{"item_title": "Translations"}	draft	f	2026-09-03 05:25:28.610181+00
221	f1d7852c-bdea-4800-ad5e-d136b0b5a712	uk	{"item_title": "Переклади"}	draft	f	2026-08-24 14:24:29.669683+00
222	adddbefc-7afa-4f9b-b337-81a3434760c4	uk	{"static_content_body": "<h1>Привіт, Камі.</h1><h2>Чиста основа для всього, що буде далі.</h2><p>Твій новий сайт уже працює. Додавай контент, формуй структуру й розвивай його далі.</p>"}	draft	f	2026-08-29 08:14:00.95826+00
223	f4c26c79-93b8-4cf2-b56a-647fd1b13b75	en	{"title":"System Manager","description":"Manage system settings, domains and secrets.","handlers":{"manage":{"title":"Manage","actions":{"overview":{"title":"System"},"settings":{"title":"System settings"},"settingsSave":{"title":"Save system settings"},"languages":{"title":"Languages"},"languagesSave":{"title":"Save languages"},"domains":{"title":"Domains"},"domainEdit":{"title":"Edit domain"},"domainSave":{"title":"Save domain"},"secrets":{"title":"Secrets"},"secretSave":{"title":"Save secret"},"secretDelete":{"title":"Delete secret"}}}},"phrases":{"system":"System","system_help":"Installation-level settings and site configuration.","system_settings":"System settings","system_settings_help":"Global values used by the Kami installation. Domain-overridable values can be changed for individual sites.","languages":"Languages","languages_help":"Choose which languages are available to sites in this Kami installation.","language":"Language","code":"Code","active":"Active","used_by_domains":"Used by domains","languages_saved":"System languages saved.","domains":"Domains","domains_help":"Manage sites, their aliases and domain-specific settings.","secrets":"Secrets","secrets_help":"Encrypted credentials stored by SecretStore. Existing values are never displayed.","open":"Open","save":"Save","cancel":"Cancel","create_domain":"Create domain","edit_domain":"Edit domain","new_domain":"New domain","domain_name":"Domain name","title":"Title","description":"Description","theme":"Theme","aliases":"Aliases","aliases_help":"One hostname per line.","domain_settings":"Domain settings","domain_overrides":"Global overrides","domain_overrides_help":"Enable an override only when this domain should differ from the global value.","override":"Override global value","global_value":"Global value","back_to_system":"Back to system","back_to_domains":"Back to domains","domain_saved":"Domain saved.","settings_saved":"System settings saved.","secret_saved":"Secret saved.","secret_deleted":"Secret deleted.","namespace":"Namespace","secret_name":"Secret name","secret_value":"Secret value","scope":"Scope","global":"Global","add_secret":"Add or replace secret","stored_secrets":"Stored secrets","updated":"Updated","actions":"Actions","delete":"Delete","confirm_delete_secret":"Delete this stored secret?","no_secrets":"No secrets stored.","setting_usergroup_root":"Root user group","setting_usergroup_root_help":"System group that bypasses normal access checks.","setting_usergroup_guest":"Guest user group","setting_usergroup_guest_help":"System group used for unauthenticated visitors.","setting_session_timeout":"Session timeout","setting_session_timeout_help":"Inactive session lifetime in seconds.","setting_default_timezone":"Default timezone","setting_default_timezone_help":"Default display timezone. Server-side processing remains in UTC.","setting_cache_enabled":"Application cache","setting_cache_enabled_help":"Default policy for domain application caching. The cache backend itself is configured in config.php.","setting_cache_ttl":"Default cache TTL","setting_cache_ttl_help":"Default domain application cache lifetime in seconds.","setting_usergroup_default":"Default user group","setting_usergroup_default_help":"Global group assigned to newly registered users.","setting_languages":"Enabled languages","setting_languages_help":"Languages available on this domain.","setting_default_language":"Default language","setting_default_language_help":"Primary language used when no language is explicitly selected.","setting_lifecycle_plugins":"Lifecycle plugins","setting_lifecycle_plugins_help":"Plugins initialized for every page request even when they are not used by a wrapper.","setting_system_css":"System CSS","setting_system_css_help":"Stylesheets exposed through the system_css template parameter. One path per item; order is preserved.","setting_system_js":"System JavaScript","setting_system_js_help":"JavaScript files exposed through the system_js template parameter. One path per item; order is preserved.","setting_system_custom_code":"System custom code","setting_system_custom_code_help":"Trusted raw HTML exposed through the system_custom_code template parameter. Script tags are allowed."}}	draft	f	2026-08-28 16:29:02.013422+00
224	f4c26c79-93b8-4cf2-b56a-647fd1b13b75	uk	{"title":"Керування системою","description":"Системні налаштування, домени та секрети.","handlers":{"manage":{"title":"Manage","actions":{"overview":{"title":"System"},"settings":{"title":"System settings"},"settingsSave":{"title":"Save system settings"},"languages":{"title":"Languages"},"languagesSave":{"title":"Save languages"},"domains":{"title":"Domains"},"domainEdit":{"title":"Edit domain"},"domainSave":{"title":"Save domain"},"secrets":{"title":"Secrets"},"secretSave":{"title":"Save secret"},"secretDelete":{"title":"Delete secret"}}}},"phrases":{"system":"Система","system_help":"Налаштування інсталяції та конфігурація сайтів.","system_settings":"Системні налаштування","system_settings_help":"Глобальні значення інсталяції Kami. Параметри з оверрайдом можна змінювати окремо для кожного домену.","languages":"Мови","languages_help":"Обери мови, які можуть використовувати сайти цієї інсталяції Kami.","language":"Мова","code":"Код","active":"Активна","used_by_domains":"Використовується доменами","languages_saved":"Системні мови збережено.","domains":"Домени","domains_help":"Керування сайтами, їхніми aliases і доменними налаштуваннями.","secrets":"Секрети","secrets_help":"Зашифровані ключі та credentials у SecretStore. Збережені значення ніколи не показуються.","open":"Відкрити","save":"Зберегти","cancel":"Скасувати","create_domain":"Створити домен","edit_domain":"Редагувати домен","new_domain":"Новий домен","domain_name":"Доменне ім'я","title":"Назва","description":"Опис","theme":"Тема","aliases":"Aliases","aliases_help":"Одне ім'я хоста на рядок.","domain_settings":"Налаштування домену","domain_overrides":"Глобальні оверрайди","domain_overrides_help":"Увімкни оверрайд лише якщо цей домен має відрізнятися від глобального значення.","override":"Перевизначити глобальне значення","global_value":"Глобальне значення","back_to_system":"Назад до системи","back_to_domains":"Назад до доменів","domain_saved":"Домен збережено.","settings_saved":"Системні налаштування збережено.","secret_saved":"Секрет збережено.","secret_deleted":"Секрет видалено.","namespace":"Namespace","secret_name":"Назва секрету","secret_value":"Значення секрету","scope":"Scope","global":"Глобально","add_secret":"Додати або замінити секрет","stored_secrets":"Збережені секрети","updated":"Оновлено","actions":"Дії","delete":"Видалити","confirm_delete_secret":"Видалити цей секрет?","no_secrets":"Секретів ще немає.","setting_usergroup_root":"Root-група","setting_usergroup_root_help":"Системна група, яка обходить звичайні перевірки доступу.","setting_usergroup_guest":"Guest-група","setting_usergroup_guest_help":"Системна група для неавторизованих відвідувачів.","setting_session_timeout":"Таймаут сесії","setting_session_timeout_help":"Час життя неактивної сесії у секундах.","setting_default_timezone":"Часовий пояс за замовчуванням","setting_default_timezone_help":"Часовий пояс для відображення часу. Серверна обробка залишається в UTC.","setting_cache_enabled":"Кеш застосунку","setting_cache_enabled_help":"Глобальна політика кешування для доменів. Сам cache backend налаштовується у config.php.","setting_cache_ttl":"TTL кешу за замовчуванням","setting_cache_ttl_help":"Стандартний час життя доменного application cache у секундах.","setting_usergroup_default":"Група користувача за замовчуванням","setting_usergroup_default_help":"Глобальна група, яку отримує новий зареєстрований користувач.","setting_languages":"Активні мови","setting_languages_help":"Мови, доступні на цьому домені.","setting_default_language":"Мова за замовчуванням","setting_default_language_help":"Основна мова домену, якщо іншу явно не вибрано.","setting_lifecycle_plugins":"Lifecycle-плагіни","setting_lifecycle_plugins_help":"Плагіни, які ініціалізуються для кожного запиту сторінки, навіть якщо вони не використовуються у wrapper.","setting_system_css":"Системні CSS","setting_system_css_help":"Таблиці стилів для параметра шаблону system_css. Один шлях на елемент, порядок зберігається.","setting_system_js":"Системні JavaScript","setting_system_js_help":"JavaScript-файли для параметра шаблону system_js. Один шлях на елемент, порядок зберігається.","setting_system_custom_code":"Довільний системний код","setting_system_custom_code_help":"Довільний trusted HTML для параметра шаблону system_custom_code. Теги script дозволені."}}	draft	f	2026-08-28 16:29:02.013422+00
225	11abbb1e-46b8-48c9-8bf0-7a401359ee07	uk	{"title": "System"}	draft	f	2026-08-28 16:29:02.013422+00
226	11abbb1e-46b8-48c9-8bf0-7a401359ee07	en	{"title": "System"}	draft	f	2026-08-28 16:29:02.013422+00
227	4e19dfae-e1de-4bbe-bf49-cbacd3f52f12	en	{"item_title": "System"}	draft	f	2026-09-03 05:25:28.612132+00
228	4e19dfae-e1de-4bbe-bf49-cbacd3f52f12	uk	{"item_title": "Система"}	draft	f	2026-08-24 14:24:29.671768+00
229	3b47fd2b-1227-4bb1-a960-f1ef114b2e87	en	{"title": "Users and access", "phrases": {"back": "Back", "edit": "Edit", "open": "Open", "save": "Save", "email": "Email", "group": "Group", "pages": "Pages", "users": "Users", "active": "Active", "cancel": "Cancel", "delete": "Delete", "status": "Status", "system": "system", "actions": "Actions", "content": "Content types", "has_api": "Allow API access", "plugins": "Plugin handlers", "inactive": "Inactive", "new_user": "New user", "username": "Username", "verified": "Verified", "is_active": "Active", "new_group": "New group", "group_name": "System name", "groups_acl": "Groups and ACL", "pages_help": "Pages are explicit resources and already belong to a domain.", "unverified": "Unverified", "users_help": "Each user belongs to exactly one global group.", "create_user": "Create user", "description": "Description", "group_title": "Title", "permissions": "Permissions", "content_help": "Core capabilities are view, create, edit and delete. Plugin-specific workflow permissions belong to plugins.", "content_type": "Content type", "create_group": "Create group", "has_api_help": "Users in this group can create and use personal API tokens within their existing permissions.", "new_password": "New password", "plugins_help": "A checked handler grants this group access to that plugin capability.", "users_access": "Users and access", "back_to_users": "Back to users", "password_help": "Leave empty to keep the current password. New users may also be created without a password for external authentication.", "back_to_groups": "Back to groups", "groups_acl_help": "Group permissions for pages, plugin handlers and content types.", "users_access_help": "Manage user accounts, groups and explicit ACL permissions.", "email_verification": "Email verification", "confirm_delete_group": "Delete this group?"}, "handlers": {"manage": {"title": "Manage", "actions": {"acl": {"title": "Permissions"}, "users": {"title": "Users"}, "groups": {"title": "Groups"}, "aclSave": {"title": "Save permissions"}, "overview": {"title": "Users and access"}, "userEdit": {"title": "Edit user"}, "userSave": {"title": "Save user"}, "groupEdit": {"title": "Edit group"}, "groupSave": {"title": "Save group"}, "groupDelete": {"title": "Delete group"}}}}, "description": "Manage users, groups and access control."}	draft	f	2026-08-28 16:29:02.013422+00
230	3b47fd2b-1227-4bb1-a960-f1ef114b2e87	uk	{"title": "Користувачі та доступ", "phrases": {"back": "Назад", "edit": "Редагувати", "open": "Відкрити", "save": "Зберегти", "email": "Email", "group": "Група", "pages": "Сторінки", "users": "Користувачі", "active": "Активний", "cancel": "Скасувати", "delete": "Видалити", "status": "Статус", "system": "системна", "actions": "Дії", "content": "Типи контенту", "has_api": "Дозволити доступ до API", "plugins": "Handlers плагінів", "inactive": "Неактивний", "new_user": "Новий користувач", "username": "Ім'я користувача", "verified": "Підтверджено", "is_active": "Активний", "new_group": "Нова група", "group_name": "Системне ім'я", "groups_acl": "Групи та ACL", "pages_help": "Сторінки є окремими ресурсами й уже належать конкретному домену.", "unverified": "Не підтверджено", "users_help": "Кожен користувач належить рівно до однієї глобальної групи.", "create_user": "Створити користувача", "description": "Опис", "group_title": "Назва", "permissions": "Права", "content_help": "Базові права ядра: view, create, edit і delete. Workflow-права конкретних плагінів залишаються плагінам.", "content_type": "Тип контенту", "create_group": "Створити групу", "has_api_help": "Користувачі цієї групи можуть створювати й використовувати власні API-токени в межах своїх поточних дозволів.", "new_password": "Новий пароль", "plugins_help": "Позначений handler надає цій групі доступ до відповідної можливості плагіна.", "users_access": "Користувачі та доступ", "back_to_users": "Назад до користувачів", "password_help": "Залиш порожнім, щоб не змінювати пароль. Нового користувача можна створити без пароля для зовнішньої авторизації.", "back_to_groups": "Назад до груп", "groups_acl_help": "Права груп на сторінки, handlers плагінів і типи контенту.", "users_access_help": "Керування акаунтами, групами та явними ACL-правами.", "email_verification": "Підтвердження email", "confirm_delete_group": "Видалити цю групу?"}, "handlers": {"manage": {"title": "Manage", "actions": {"acl": {"title": "Permissions"}, "users": {"title": "Users"}, "groups": {"title": "Groups"}, "aclSave": {"title": "Save permissions"}, "overview": {"title": "Users and access"}, "userEdit": {"title": "Edit user"}, "userSave": {"title": "Save user"}, "groupEdit": {"title": "Edit group"}, "groupSave": {"title": "Save group"}, "groupDelete": {"title": "Delete group"}}}}, "description": "Керування користувачами, групами та правами доступу."}	draft	f	2026-08-28 16:29:02.013422+00
231	c6b29462-4207-45db-be29-9579d9521d5f	uk	{"title": "Користувачі та доступ"}	draft	f	2026-08-28 16:29:02.013422+00
232	c6b29462-4207-45db-be29-9579d9521d5f	en	{"title": "Users and access"}	draft	f	2026-08-28 16:29:02.013422+00
233	7d394773-29a3-42b5-8970-8fbee6284d80	en	{"item_title": "Users and access"}	draft	f	2026-09-03 05:25:28.611487+00
234	7d394773-29a3-42b5-8970-8fbee6284d80	uk	{"item_title": "Користувачі та доступ"}	draft	f	2026-08-24 14:24:29.671123+00
235	33b60373-705d-4b9c-892a-40fda0aa64dc	uk	{"title": "Шаблон елемента", "options": [{"title": "За замовчуванням", "value": "default"}, {"title": "Контейнер", "value": "container"}, {"title": "Картка", "value": "card"}, {"title": "Компактний", "value": "compact"}], "description": "Шаблон для виведення елемента статичного контенту."}	draft	f	2026-08-28 16:29:02.013422+00
236	990d42d6-edac-4afe-aa60-9ce338d4163e	uk	{"title": "Static content block", "description": "Wrapper for several content items."}	draft	f	2026-08-30 03:57:26.356393+00
237	5995a472-db4f-4737-bded-ecb86e441981	en	{"title": "Block template", "options": [{"title": "Default", "value": "default"}, {"title": "Card", "value": "card"}, {"title": "Compact", "value": "compact"}, {"title": "Responsive grid", "value": "responsive_grid"}], "description": "Template used to render the content block."}	draft	f	2026-08-28 16:29:02.013422+00
238	5995a472-db4f-4737-bded-ecb86e441981	uk	{"title": "Шаблон блока", "options": [{"title": "За замовчуванням", "value": "default"}, {"title": "Картка", "value": "card"}, {"title": "Компактний", "value": "compact"}, {"title": "Адаптивна сітка", "value": "responsive_grid"}], "description": "Шаблон для виведення блока контенту."}	draft	f	2026-08-29 19:55:25.30301+00
239	3b2d0e2f-12ae-4d3f-8a45-b9fc0fb71959	en	{"title": "Media", "phrases": {"move": "Move", "files": "Files", "media": "Media", "cancel": "Cancel", "delete": "Delete", "rename": "Rename", "select": "Select", "upload": "Upload", "folders": "Folders", "refresh": "Refresh", "copy_url": "Copy URL", "media_help": "Browse and manage public files stored in Media.", "new_folder": "New folder", "destination": "Destination", "folder_name": "Folder name", "empty_folder": "This folder is empty.", "confirm_delete": "Delete this item? Existing links may stop working."}, "handlers": {"view": {"title": "View", "actions": {"browser": {"title": "Media browser"}, "listFiles": {"title": "Browse media files"}}}, "manage": {"title": "Manage", "actions": {"move": {"title": "Move media item"}, "delete": {"title": "Delete media item"}, "rename": {"title": "Rename media item"}, "upload": {"title": "Upload media files"}, "createFolder": {"title": "Create media folder"}}}}, "settings": {"max_upload_size": {"title": "Maximum upload size (MB)", "description": "Maximum size of one uploaded file in MB. PHP/web-server limits still apply. If upload_max_filesize, post_max_size or another server limit is lower, ask the server administrator to change it; Media does not use chunked or streaming uploads."}, "allowed_extensions": {"title": "Allowed file extensions", "description": "Comma-separated list of file extensions accepted by Media. Executable, server-side and active web content remains blocked by Media security policy even if added here."}}, "description": "Manage public media files."}	draft	f	2026-08-28 16:29:02.013422+00
240	3b2d0e2f-12ae-4d3f-8a45-b9fc0fb71959	uk	{"title": "Медіа", "phrases": {"move": "Перемістити", "files": "Файли", "media": "Медіа", "cancel": "Скасувати", "delete": "Видалити", "rename": "Перейменувати", "select": "Обрати", "upload": "Завантажити", "folders": "Теки", "refresh": "Оновити", "copy_url": "Копіювати URL", "media_help": "Перегляд і керування публічними файлами Media.", "new_folder": "Нова тека", "destination": "Призначення", "folder_name": "Назва теки", "empty_folder": "Ця тека порожня.", "confirm_delete": "Видалити цей елемент? Існуючі посилання можуть перестати працювати."}, "handlers": {"view": {"title": "View", "actions": {"browser": {"title": "Media browser"}, "listFiles": {"title": "Browse media files"}}}, "manage": {"title": "Manage", "actions": {"move": {"title": "Move media item"}, "delete": {"title": "Delete media item"}, "rename": {"title": "Rename media item"}, "upload": {"title": "Upload media files"}, "createFolder": {"title": "Create media folder"}}}}, "settings": {"max_upload_size": {"title": "Maximum upload size (MB)", "description": "Maximum size of one uploaded file in MB. PHP/web-server limits still apply. If upload_max_filesize, post_max_size or another server limit is lower, ask the server administrator to change it; Media does not use chunked or streaming uploads."}, "allowed_extensions": {"title": "Allowed file extensions", "description": "Comma-separated list of file extensions accepted by Media. Executable, server-side and active web content remains blocked by Media security policy even if added here."}}, "description": "Керування публічними медіафайлами."}	draft	f	2026-08-28 16:29:02.013422+00
241	a0455cb5-a499-493e-bb27-d253435beaca	uk	{"item_title": "Сторінки"}	draft	f	2026-08-24 14:24:29.66536+00
242	a74fe70f-b820-443e-8daf-c413d34eb110	uk	{"item_title": "Навігація"}	draft	f	2026-08-24 14:24:29.666837+00
243	f2d5f754-cbff-4e6c-a945-38d229f4346e	uk	{"title": "Page manager"}	draft	f	2026-08-28 16:29:02.013422+00
244	b4d620ee-9c26-4bc8-9694-a2ca59db928f	uk	{"title": "Menu manager"}	draft	f	2026-08-28 16:29:02.013422+00
245	4e8b7b25-7777-4974-892f-a56fd33681d0	uk	{"title": "Головна"}	draft	f	2026-08-28 16:29:02.013422+00
246	dda755fd-43d9-4e4d-be03-6197b5983b79	en	{"title": "Pagination", "description": "Renders reusable pagination navigation blocks."}	draft	f	2026-08-28 16:29:02.013422+00
247	a7349b6f-7771-4be9-8bca-f321c812d090	uk	{"title": "ID групи користувачів", "description": "Цілочисельний ідентифікатор групи користувачів."}	published	f	2026-08-28 16:29:02.013422+00
248	a7349b6f-7771-4be9-8bca-f321c812d090	en	{"title": "User group ID", "description": "Integer identifier of a user group."}	published	t	2026-08-28 16:29:02.013422+00
249	ff959899-1251-4b1d-81c2-8563f24ca150	uk	{"title": "Керування контентом"}	draft	f	2026-08-28 16:29:02.013422+00
250	8f37a3d0-e84a-4e53-a2c3-4fbbf47c977c	en	{"title": "Visible to groups", "description": "User groups that can see this navigation element. Leave empty to show it to everyone."}	draft	f	2026-08-28 16:29:02.013422+00
251	8f37a3d0-e84a-4e53-a2c3-4fbbf47c977c	uk	{"title": "Видиме для груп", "description": "Групи користувачів, які можуть бачити цей елемент навігації. Залиште порожнім, щоб показувати його всім."}	draft	f	2026-08-28 16:29:02.013422+00
252	5cc03c17-3833-4003-9d25-13fdd5f641cd	uk	{"title": "Медіа"}	draft	f	2026-08-28 16:29:02.013422+00
253	80b7d96d-2d2d-4533-bd75-6295e744a792	uk	{"item_title": "Плагіни"}	draft	f	2026-08-24 14:24:29.667852+00
254	e3d061bb-176e-4c5c-a3e2-d95a333aa307	uk	{"item_title": "Контент"}	draft	f	2026-08-28 14:53:04.203937+00
255	121407bf-536c-4e06-9b4a-ce45eaf9e497	uk	{"item_title": "Медіа"}	draft	f	2026-08-28 14:52:12.976833+00
256	10a83691-48f7-48c8-b6bd-d5fe3dfe2282	en	{"title": "Media", "description": "Public URL with optional selection from the Media browser."}	draft	f	2026-08-28 16:29:02.013422+00
257	10a83691-48f7-48c8-b6bd-d5fe3dfe2282	uk	{"title": "Медіа", "description": "Публічний URL із можливістю вибору через медіабраузер."}	draft	f	2026-08-28 16:29:02.013422+00
258	cb599413-35fe-4972-8cc7-21ec10d7f7f7	uk	{"title": "Обкладинка", "description": ""}	draft	f	2026-08-28 16:29:02.013422+00
259	159467a7-5444-4cb2-a050-a790a0111ab1	uk	{"title": "Теги", "description": ""}	draft	f	2026-08-28 16:29:02.013422+00
260	3798b10a-78ed-44c2-92d8-e8d26fda3ac8	en	{"title": "Autocomplete", "description": "Text value with suggestions collected from indexed text fields; new values are allowed."}	draft	f	2026-08-28 16:29:02.013422+00
261	3798b10a-78ed-44c2-92d8-e8d26fda3ac8	uk	{"title": "Автодоповнення", "description": "Текстове значення з підказками, зібраними з індексованих текстових полів; можна вводити нові значення."}	draft	f	2026-08-28 16:29:02.013422+00
262	2c4b18d5-8bdb-4c37-abdb-6324e285f02f	en	{"title": "Root"}	draft	f	2026-08-28 16:29:02.013422+00
263	33b3a2a8-08e5-4d3a-ab3e-427b5e1da727	en	{"title": "User"}	draft	f	2026-09-03 16:48:01.976859+00
264	4324cc80-e86d-4b64-9c0d-9adea5250cae	en	{"title": "Guest"}	draft	f	2026-08-28 16:29:02.013422+00
295	6b53edee-0889-409a-b195-e3dd0ca56f91	en	{"tags": ["Articles", "Kami"], "summary": "An article is only one example of a content structure. Its fields can be added, removed, or changed to match the needs of a particular project.", "article_body": "<p>Each field can have its own purpose and behavior: some values may be translated, while others can remain the same in every language. The template then decides how that content is presented on the page.</p><p>The important part is the separation between content, structure, and presentation. You can change one without having to rebuild everything else around it.</p>", "display_title": "This Is Just an Article"}	draft	f	2026-09-01 18:01:14.09017+00
265	ae04e161-6ec8-48e1-a07d-fe892fad9810	en	{"title": "Main page layout", "wrappers": {"top_plugins": {"title": "Top section plugins", "description": "This is where plugins for the top of the page are placed, such as navigation, language switcher, etc."}, "head_plugins": {"title": "Head plugins", "description": "The output of these plugins is located inside the <head> tag of the page and is not visible for the user. SEO plugins are usually located here."}, "hero_plugins": {"title": "Hero section plugins", "description": "This is a place for hero section plugins."}, "footer_plugins": {"title": "Footer section plugins"}, "content_plugins": {"title": "Content section plugins", "description": "This is where plugins for the top of the page are placed, such as navigation, hero section, etc."}}}	draft	f	2026-08-28 16:29:02.013422+00
266	befa6b10-2cbd-42a5-a7b0-bb011d8f7a6b	en	{"title": "Simple page layout", "wrappers": {"top_plugins": {"title": "Top section plugins", "description": "This is where plugins for the top of the page are placed, such as navigation, hero section, etc."}, "head_plugins": {"title": "Head plugins", "description": "The output of these plugins is located inside the <head> tag of the page and is not visible for the user. SEO plugins are usually located here."}, "footer_plugins": {"title": "Footer section plugins"}, "content_plugins": {"title": "Content section plugins", "description": "This is a main content section."}}}	draft	f	2026-08-28 16:29:02.013422+00
267	e4689b90-20de-4f33-a71a-4cbfc9d05a27	en	{"title": "Admin layout", "wrappers": {"sidebar": {"title": "Left sidebar", "description": "Left sidebar in admin area (admin navigation etc)"}, "content_top": {"title": "Top section", "description": "..."}, "head_plugins": {"title": "Head plugins", "description": "The output of these plugins is located inside the <head> tag of the page and is not visible for the user. SEO plugins are usually located here."}, "content_middle": {"title": "Middle section", "description": "Work area."}, "content_bottoms": {"title": "Footer section plugins"}}}	draft	f	2026-08-28 16:29:02.013422+00
268	aca53e19-27a2-40f8-97d2-105428ed9c3a	uk	{"title": "Заголовок", "description": "Необов’язковий публічний заголовок блока."}	draft	f	2026-08-28 16:29:02.013422+00
269	12dbf4d1-e811-4eec-b4da-ea8fb488d40c	uk	{"title": "Елементів у рядку", "description": "Максимальна кількість елементів в одному рядку."}	draft	f	2026-08-28 16:29:02.013422+00
270	4aa20cf5-36f7-4e98-a57d-13846a1a06c5	uk	{"title": "Мінімальна ширина елемента", "description": "Мінімальна ширина елемента в пікселях."}	draft	f	2026-08-28 16:29:02.013422+00
271	cffa0508-28a8-4377-93de-d094bceccce0	uk	{"display_title": ""}	draft	f	2026-08-25 11:16:33.800136+00
272	2d83b791-abfd-46fa-88db-221c7243a4f1	en	{"title": "Mailer", "settings": {"host": {"title": "SMTP host"}, "port": {"title": "SMTP port"}, "mailer": {"title": "Mail transport", "options": {"mail": "PHP mail()", "smtp": "SMTP", "sendmail": "Sendmail"}, "description": "Transport used to deliver messages."}, "charset": {"title": "Character set"}, "timeout": {"title": "Connection timeout", "description": "Timeout in seconds for the mail transport."}, "username": {"title": "SMTP username"}, "from_name": {"title": "From name"}, "encryption": {"title": "SMTP encryption", "options": {"": "None", "ssl": "SSL", "tls": "TLS"}}, "from_email": {"title": "From email"}, "reply_to_name": {"title": "Reply-to name"}, "reply_to_email": {"title": "Reply-to email"}}, "description": "Provides email message composition and delivery for other plugins."}	draft	f	2026-08-28 16:29:02.013422+00
273	dc390832-8af1-4700-8902-b02758820631	uk	{"title": "Теми"}	draft	f	2026-09-03 09:06:32.764435+00
274	7a0c0b7b-e5de-4422-acd6-7d145dcfac73	en	{"title": "Notifications", "handlers": {"view": {"title": "Notifications", "actions": {"get": {"title": "Get notifications"}, "view": {"title": "Notification container"}}}}, "settings": {"expire": {"title": "Notification lifetime", "description": "Maximum notification lifetime in seconds. Set to 0 to disable expiration."}}, "description": "Transient client-context notifications delivered through the standard AJAX endpoint."}	draft	f	2026-08-28 16:29:02.013422+00
275	7a0c0b7b-e5de-4422-acd6-7d145dcfac73	uk	{"title": "Сповіщення", "handlers": {"view": {"title": "Сповіщення", "actions": {"get": {"title": "Отримати сповіщення"}, "view": {"title": "Контейнер сповіщень"}}}}, "settings": {"expire": {"title": "Notification lifetime", "description": "Maximum notification lifetime in seconds. Set to 0 to disable expiration."}}, "description": "Тимчасові сповіщення контексту клієнта, що доставляються через стандартний AJAX endpoint."}	draft	f	2026-08-28 16:29:02.013422+00
276	842fc2e0-20f9-427e-aaa3-aaa245e473bc	uk	{"title": "User account"}	draft	f	2026-08-28 16:29:02.013422+00
277	121407bf-536c-4e06-9b4a-ce45eaf9e497	en	{"item_title": "Media"}	draft	f	2026-09-03 05:25:28.610831+00
278	e3d061bb-176e-4c5c-a3e2-d95a333aa307	en	{"item_title": "Content"}	draft	f	2026-09-03 05:25:28.609534+00
279	a74fe70f-b820-443e-8daf-c413d34eb110	en	{"item_title": "Navigation"}	draft	f	2026-09-03 05:25:28.608209+00
280	80b7d96d-2d2d-4533-bd75-6295e744a792	en	{"item_title": "Plugins"}	draft	f	2026-09-03 05:25:28.608882+00
282	7a8a658a-d9f1-412f-9aeb-a8f203062f27	uk	{"item_title": "Блог"}	draft	f	2026-09-06 02:22:54.539073+00
283	bbf7c42e-9e6b-4ee5-b20c-4d15a65565cb	uk	{"item_title": "Головна"}	draft	f	2026-01-30 20:09:35.688355+00
284	a3c1739e-94a9-4397-b54b-3084242ee292	uk	{"item_title": "Про"}	draft	f	2026-01-30 20:09:35.691368+00
285	b7035ae7-3fc5-4da0-a099-f7a809ea5941	uk	{"item_title": "Мій аккаунт"}	draft	f	2026-09-05 05:11:26.240856+00
286	13c52e01-3894-4a06-8b5f-df2d4f55f966	uk	{"item_title": "Панель адміністратора"}	draft	f	2026-09-05 05:11:26.242482+00
287	e35594b9-184e-4c0f-bc2d-9eee9959b343	uk	{"static_content_body": "<h2>Від простішого до складного - одна модель.</h2><p><img src=\\"/media/2026/08/stone_garden_2.webp\\">Це статичний HTML-блок. Такі блоки можна створювати, редагувати й перекладати окремо від структури сайту, а потім додавати на сторінки в потрібних місцях.</p>"}	draft	f	2026-08-29 13:46:59.396414+00
288	16dfed6d-74dd-44b8-82c2-0503919bb6db	en	{"title": "Article", "description": "Basic publishable article."}	draft	f	2026-08-29 19:51:21.197214+00
289	cb599413-35fe-4972-8cc7-21ec10d7f7f7	en	{"title": "Article preview"}	draft	f	2026-08-29 19:54:12.341989+00
290	16dfed6d-74dd-44b8-82c2-0503919bb6db	uk	{"title": "Стаття", "description": "Базова публічна стаття."}	draft	f	2026-08-29 19:57:18.523361+00
291	aca53e19-27a2-40f8-97d2-105428ed9c3a	en	{"title": "Display title", "description": "Optional public title displayed with the content block."}	draft	f	2026-08-30 09:54:06.185659+00
292	98a8eb58-ea4e-4090-aed6-dadcc6be76d9	uk	{"title": "Елементи контенту", "description": "Елементи контенту, що входять до цього блока."}	draft	f	2026-08-30 09:54:06.185659+00
293	12dbf4d1-e811-4eec-b4da-ea8fb488d40c	en	{"title": "Items per row", "description": "Maximum number of items in one row."}	draft	f	2026-08-30 09:54:06.185659+00
294	4aa20cf5-36f7-4e98-a57d-13846a1a06c5	en	{"title": "Minimum item width", "description": "Minimum item width in pixels."}	draft	f	2026-08-30 09:54:06.185659+00
349	051a0a79-f02a-4dc9-b3ce-9631139fe486	uk	{"menu_title": "User context menu", "menu_description": "Preferences, credentials, admin panel etc."}	draft	f	2026-09-05 05:11:26.238699+00
296	019dc822-41f5-4d3c-b01b-7c2e72a32df7	en	{"tags": ["interior"], "summary": "A room can feel completely different without changing its size. Sometimes the biggest difference comes from simple things: where the light falls, how objects are arranged, and how much empty space remains between them.", "article_body": "<p><strong>Natural light</strong> makes surfaces, textures, and colors change throughout the day. A shape that feels flat in the morning may become the strongest element in the room by evening.</p><p><strong>Empty space </strong>matters just as much as the objects placed within it. Giving things room to breathe can make even a small space feel calmer and more deliberate.</p><p>Good spaces rarely depend on a single dramatic feature. More often, they come from a balance of light, shape, texture, and the quiet areas in between.</p>", "display_title": "Light, Shape and Space"}	draft	f	2026-08-31 11:28:53.204928+00
297	e78310dd-6c41-436b-b0a7-ec91fdd9a1f7	en	{"tags": ["interior"], "summary": "Improving a workspace does not always require new furniture or a complete redesign. A few small changes can often make it more comfortable and easier to use.", "article_body": "<h2>Remove what you do not need</h2><p>Keep the things you use regularly within reach and move everything else out of the way. A little more free space can make the whole workspace feel less distracting.</p><h2>Improve the light</h2><p>Good lighting makes long periods of work easier. Use natural light when possible, and place additional lighting where it helps without creating glare or harsh shadows.</p><h2>Leave room for change</h2><p>A workspace does not have to be finished forever. Keep enough flexibility to move things around, add something useful, or remove something that no longer works for you.</p><p>Small adjustments are easy to make, easy to reverse, and often enough to noticeably change how a space feels.</p>", "display_title": "Three Small Changes"}	draft	f	2026-09-06 01:56:37.573783+00
298	b796252b-e22e-4714-ba0e-69b66aa7926d	en	{"title": "Article category", "description": ""}	draft	f	2026-08-31 09:43:48.739955+00
299	159467a7-5444-4cb2-a050-a790a0111ab1	en	{"title": "Tags", "description": ""}	draft	f	2026-08-31 11:02:54.140292+00
300	74e0be37-2b6d-4b01-b5f8-048a6e309b71	en	{"title": "Article categories", "description": ""}	draft	f	2026-08-31 11:15:19.677557+00
301	c86cc1b0-2a8d-41f1-a666-f70f2fd6e0d5	en	{"summary": "Short description for article category here.\\r\\nCategory is an ordinary content item, so, you can edit, delete and create new categories in Content manager.", "display_title": "About Kami"}	draft	f	2026-09-01 17:57:22.439866+00
302	3045aa8a-b1a9-497f-8c6b-f90207d4e4e8	en	{"summary": "This is a test category. You can rename and use it for your articles, or delete and make new ones.", "display_title": "About everything"}	draft	f	2026-09-01 17:57:13.045008+00
303	39db37bf-1cdf-4fd6-99f1-6acbccbb7dbe	uk	{"item_title": "Вийти"}	draft	f	2026-09-05 05:11:26.243228+00
304	39db37bf-1cdf-4fd6-99f1-6acbccbb7dbe	en	{"item_title": "Log out"}	draft	f	2026-09-03 16:51:57.181564+00
305	a66e9c43-ea3b-403d-a7a5-756ca09015ee	en	{"title": "Article Viewer", "phrases": {"all": "All", "tags": "Tags", "authors": "Authors", "articles": "Articles", "read_more": "Read more", "updated_at": "Updated", "no_articles": "No articles found.", "published_at": "Published", "items_per_page": "Items per page", "latest_articles": "Latest articles", "back_to_articles": "Back to articles"}, "handlers": {"list": {"title": "Article list", "actions": {"list": {"title": "List articles"}}, "instance_params": {"items_per_page": {"title": "Items per page", "description": "Override the default number of articles displayed per page. Leave empty to use the plugin setting. Use 0 to display all articles."}, "show_pagination": {"title": "Show pagination", "description": "Display pagination controls for this article list."}, "articles_category_ids": {"title": "Article categories", "description": "Select which article categories to display. Leave empty to display articles from all categories."}}}, "view": {"title": "Single article", "actions": {"view": {"title": "View article"}}, "instance_params": {"items_per_page": {"title": "Items per page", "description": "Override the default number of articles displayed per page. Leave empty to use the plugin setting. Use 0 to display all articles."}, "show_pagination": {"title": "Show pagination", "description": "Display pagination controls for this article list."}, "articles_category_ids": {"title": "Article categories", "description": "Select which article categories to display. Leave empty to display articles from all categories."}}}}, "settings": {"default_count": {"title": "Default items per page", "description": "Default number of articles displayed per page."}, "items_count_values": {"title": "Items per page options", "description": "Available numbers of articles per page. Use 0 to include an all-articles option."}, "items_count_selector": {"title": "Allow items per page selection", "description": "Can users choose how many articles to display on a page?"}}, "description": "Display article lists and individual articles on the front end."}	draft	f	2026-08-31 20:04:19.149838+00
306	a66e9c43-ea3b-403d-a7a5-756ca09015ee	uk	{"title": "Перегляд статей", "phrases": {"all": "Усі", "tags": "Теги", "authors": "Автори", "articles": "Статті", "read_more": "Читати далі", "updated_at": "Оновлено", "no_articles": "Статей не знайдено.", "published_at": "Опубліковано", "items_per_page": "На сторінці", "latest_articles": "Останні статті", "back_to_articles": "Назад до статей"}, "handlers": {"list": {"title": "Список статей", "actions": {"list": {"title": "Показати список статей"}}, "instance_params": {"items_per_page": {"title": "Items per page", "description": "Override the default number of articles displayed per page. Leave empty to use the plugin setting. Use 0 to display all articles."}, "show_pagination": {"title": "Show pagination", "description": "Display pagination controls for this article list."}, "articles_category_ids": {"title": "Article categories", "description": "Select which article categories to display. Leave empty to display articles from all categories."}}}, "view": {"title": "Окрема стаття", "actions": {"view": {"title": "Показати статтю"}}, "instance_params": {"items_per_page": {"title": "Items per page", "description": "Override the default number of articles displayed per page. Leave empty to use the plugin setting. Use 0 to display all articles."}, "show_pagination": {"title": "Show pagination", "description": "Display pagination controls for this article list."}, "articles_category_ids": {"title": "Article categories", "description": "Select which article categories to display. Leave empty to display articles from all categories."}}}}, "settings": {"default_count": {"title": "Типова кількість на сторінці", "description": "Типова кількість статей, що відображаються на сторінці."}, "items_count_values": {"title": "Варіанти кількості на сторінці", "description": "Доступні варіанти кількості статей на сторінці. Значення 0 додає варіант показу всіх статей."}, "items_count_selector": {"title": "Вибір кількості на сторінці", "description": "Чи можуть користувачі обирати кількість статей на сторінці?"}}, "description": "Виведення списків статей та окремих статей на сайті."}	draft	f	2026-08-31 20:03:27.259799+00
307	b2ea1f58-00f2-4b3c-be49-5e9b64ef2de2	en	{"title": "test multiple int values", "description": ""}	draft	f	2026-08-31 14:54:04.340692+00
308	e16492a6-3ae9-43b0-9bce-742713ef4ac3	en	{"title": "About Everything"}	draft	f	2026-08-31 17:00:32.493036+00
309	caf329e2-72bd-4ec7-a6f4-cf78081af6e1	en	{"title": "Value formatter", "settings": {"date_format": {"title": "Date format", "description": "PHP date format used for date values."}, "currency_format": {"title": "Currency format", "description": "Currency output template. Use {{value}} for the formatted number and {{symbol}} for the currency symbol."}, "datetime_format": {"title": "Date and time format", "description": "PHP date format used for date and time values."}, "decimal_separator": {"title": "Decimal separator", "description": "Character used between integer and decimal parts."}, "thousands_separator": {"title": "Thousands separator", "description": "Character used to separate groups of thousands. Leave empty for no separator."}}, "description": "Formats dates, numbers, and currency values using language-specific settings."}	draft	f	2026-09-01 09:40:35.383351+00
350	b5557202-85ac-4efa-8356-247b518d93c4	uk	{"menu_title": "User sidebar menu", "menu_description": "Full navigation for user area."}	draft	f	2026-09-05 05:12:04.688561+00
351	02ea678c-49ca-4b8e-aba2-5f420a6879eb	uk	{"item_title": "Credentials"}	draft	f	2026-09-05 05:12:04.690817+00
352	3bf97882-9eb1-4591-86e0-bc5a5edf430a	uk	{"item_title": "API settings"}	draft	f	2026-09-05 05:12:04.691632+00
353	a5f7b99c-df1b-4e9b-899e-d74fbb06ecab	uk	{"item_title": "Log out"}	draft	f	2026-09-05 05:12:04.692401+00
354	2c4b18d5-8bdb-4c37-abdb-6324e285f02f	uk	{"title": "Root"}	draft	f	2026-09-05 05:16:51.640732+00
355	35f8e964-cb56-434c-9ebc-fbc827a08eb2	en	{"item_title": "test"}	draft	f	2026-09-05 14:01:46.995382+00
310	caf329e2-72bd-4ec7-a6f4-cf78081af6e1	uk	{"title": "Форматування значень", "settings": {"date_format": {"title": "Формат дати", "description": "Формат дати PHP для значень типу date."}, "currency_format": {"title": "Формат валюти", "description": "Шаблон виведення валюти. {{value}} — відформатоване число, {{symbol}} — символ валюти."}, "datetime_format": {"title": "Формат дати й часу", "description": "Формат дати PHP для значень дати й часу."}, "decimal_separator": {"title": "Десятковий розділювач", "description": "Символ між цілою та дробовою частинами числа."}, "thousands_separator": {"title": "Розділювач тисяч", "description": "Символ для розділення груп тисяч. Залиште порожнім, щоб не використовувати розділювач."}}, "description": "Форматує дати, числа та валютні значення відповідно до мовних налаштувань."}	draft	f	2026-09-01 09:40:35.383351+00
311	7f03bfc8-d082-4c3e-8653-08cf9bb9125d	en	{"title": "Category page", "description": "Page used to display this category and its articles."}	draft	f	2026-09-01 17:56:41.816846+00
312	b14eaea7-67a1-4160-9afe-a5547e8712bf	en	{"title": "Primary category", "description": ""}	draft	f	2026-09-01 17:59:57.050304+00
313	74e0be37-2b6d-4b01-b5f8-048a6e309b71	uk	{"title": "Article categories", "description": ""}	draft	f	2026-09-02 13:13:34.467263+00
314	b14eaea7-67a1-4160-9afe-a5547e8712bf	uk	{"title": "Primary category", "description": ""}	draft	f	2026-09-02 13:13:34.467263+00
315	9bda5536-e965-4359-a6ad-fdee8814a0aa	uk	{"title": "Related items", "description": ""}	draft	f	2026-09-02 13:13:34.467263+00
316	7f03bfc8-d082-4c3e-8653-08cf9bb9125d	uk	{"title": "Category page", "description": "Page used to display this category and its articles."}	draft	f	2026-09-02 13:13:34.467263+00
317	b796252b-e22e-4714-ba0e-69b66aa7926d	uk	{"title": "Article category", "description": ""}	draft	f	2026-09-02 13:13:34.467263+00
318	016b78c1-25cb-4ca6-8c55-80f0bd62db49	en	{"title": "API Access", "phrases": {"copy": "Copy", "done": "Done", "edit": "Edit", "name": "Name", "save": "Save", "view": "View", "never": "Never", "title": "API access", "token": "Token", "access": "Access", "active": "Active", "cancel": "Cancel", "copied": "Copied", "create": "Create token", "delete": "Delete", "enable": "Enable", "revoke": "Revoke", "status": "Status", "actions": "Actions", "created": "Created", "disable": "Disable", "expired": "Expired", "expires": "Expires", "revoked": "Revoked", "disabled": "Disabled", "last_used": "Last used", "no_tokens": "No API tokens yet.", "created_at": "Created at", "expires_at": "Expires at", "never_used": "Never used", "token_hint": "Stored token", "api_actions": "API actions", "description": "Create and manage personal tokens for external applications.", "permissions": "Permissions", "types_count": "{count} content types", "expires_help": "Leave empty for a token without an expiration date.", "invalid_name": "Token name is required.", "access_denied": "API access is not enabled for your user group.", "actions_count": "{count} actions", "invalid_token": "API token not found.", "token_created": "Token created", "confirm_delete": "Delete this revoked or expired token record?", "confirm_revoke": "Revoke this token permanently? It cannot be restored and applications using it will need a new token.", "content_access": "Content access", "invalid_expiry": "Expiration date must be in the future.", "no_api_actions": "No API actions are currently available.", "confirm_disable": "Temporarily disable this token? Requests using it will be rejected until you enable it again.", "deleted_success": "Token record deleted.", "edit_permission": "Edit", "enabled_success": "Token enabled.", "revoked_success": "Token revoked permanently.", "token_hint_help": "Only this short hint is stored for identification. The full token cannot be recovered.", "token_name_help": "Use a recognizable name such as Mobile app or CRM sync.", "updated_success": "Token updated successfully.", "api_actions_help": "Select the API actions this token may call. Only actions currently available to your account are shown.", "disabled_success": "Token disabled.", "create_permission": "Create", "delete_permission": "Delete", "token_created_help": "Copy this token now. It will not be shown again.", "content_access_help": "Limit the token to a subset of your current content permissions.", "no_content_permissions": "No content permissions are currently available."}, "handlers": {"manage": {"title": "Manage API access", "actions": {"tokenEdit": {"title": "Edit API token"}, "tokenList": {"title": "List API tokens"}, "tokenSave": {"title": "Save API token"}, "tokenDelete": {"title": "Delete API token"}, "tokenEnable": {"title": "Enable API token"}, "tokenRevoke": {"title": "Revoke API token"}, "tokenDisable": {"title": "Disable API token"}}}}, "description": "Manage personal API access tokens."}	draft	f	2026-09-02 14:03:19.029886+00
320	0a72ab93-78f3-4299-807e-324cd346526e	en	{"item_title": "API settings"}	draft	f	2026-09-03 16:51:57.180083+00
321	0a72ab93-78f3-4299-807e-324cd346526e	uk	{"item_title": "Налаштування API"}	draft	f	2026-09-05 05:11:26.241675+00
322	f702280f-939f-48f9-94c2-b94474e67af6	en	{"menu_title": "Admin sidebar", "menu_description": ""}	draft	f	2026-09-03 05:25:28.605289+00
323	d6365b6e-b6f6-4059-9dc7-8f0ec1269573	en	{"menu_title": "Main menu for kamicode.org", "menu_description": "Displayed on front-end in top navigation section"}	draft	f	2026-09-03 14:59:47.661879+00
324	051a0a79-f02a-4dc9-b3ce-9631139fe486	en	{"menu_title": "User context menu", "menu_description": "Preferences, credentials, admin panel etc."}	draft	f	2026-09-03 16:51:57.177205+00
325	b5557202-85ac-4efa-8356-247b518d93c4	en	{"menu_title": "User sidebar menu", "menu_description": "Full navigation for user area."}	draft	f	2026-09-04 00:54:33.26686+00
326	02ea678c-49ca-4b8e-aba2-5f420a6879eb	en	{"item_title": "Credentials"}	draft	f	2026-09-04 00:54:33.269747+00
327	a5f7b99c-df1b-4e9b-899e-d74fbb06ecab	en	{"item_title": "Log out"}	draft	f	2026-09-04 00:54:33.271861+00
328	b9d068fc-0b06-432a-9eca-11de9fc93125	en	{"item_title": "Themes"}	draft	f	2026-09-03 05:25:28.61306+00
329	bde0603f-e945-4b2c-95f5-3bd6d429ef86	en	{"item_title": "Log out"}	draft	f	2026-09-03 05:25:28.613986+00
330	618ab4f2-af51-42a3-856e-876cf1046ada	en	{"title": "Page layout with left sidebar", "wrappers": {"top_plugins": {"title": "Top section plugins", "description": "This is where plugins for the top of the page are placed, such as navigation, hero section, etc."}, "head_plugins": {"title": "Head plugins", "description": "The output of these plugins is located inside the <head> tag of the page and is not visible for the user. SEO plugins are usually located here."}, "footer_plugins": {"title": "Footer section plugins"}, "content_plugins": {"title": "Content section plugins", "description": "This is a main content section."}, "sidebar_plugins": {"title": "Left sidebar plugins", "description": "Left sidebar in user area (account navigation menu, widgets etc)"}}}	draft	f	2026-09-03 09:01:10.977748+00
331	5cc03c17-3833-4003-9d25-13fdd5f641cd	en	{"title": "Media"}	draft	f	2026-09-03 09:05:53.730895+00
332	dc390832-8af1-4700-8902-b02758820631	en	{"title": "Themes"}	draft	f	2026-09-03 09:06:13.873202+00
333	80229faa-4284-4d7c-90c8-b2403e906836	en	{"title": "Static page"}	draft	f	2026-09-03 12:02:00.709922+00
334	6925f488-c5e0-4bd2-9439-ef53b7a3f61f	en	{"title": "Blog"}	draft	f	2026-09-03 12:07:15.81355+00
335	053b11e5-3a8a-47ae-a6e2-97bd0d321a3d	en	{"item_title": "Home"}	draft	f	2026-09-03 14:59:47.664044+00
336	422800ad-bfec-4b87-a473-e10238ced719	en	{"item_title": "About Kami"}	draft	f	2026-09-03 14:59:47.666188+00
337	558821bc-781a-464e-a680-6e2a9e323edd	en	{"item_title": "About everything"}	draft	f	2026-09-03 14:59:47.666881+00
338	5fc12690-99a0-4c13-9f1e-d4838507fad9	en	{"title": "API settings"}	draft	f	2026-09-03 15:16:36.932779+00
339	3bf97882-9eb1-4591-86e0-bc5a5edf430a	en	{"item_title": "API settings"}	draft	f	2026-09-04 00:54:33.27077+00
340	c8341510-da95-4910-9ef2-e9104e0ab943	en	{"title": "API users"}	draft	f	2026-09-03 16:46:42.006383+00
341	b4f07fac-f5f6-4306-a837-855742abc702	uk	{"static_content_body": "<p class=\\"ql-align-center\\">Powered by <a href=\\"https://kamicore.org\\" rel=\\"noopener noreferrer\\" target=\\"_blank\\">KamiCore</a></p>"}	draft	f	2026-09-04 18:26:47.653965+00
342	b4f07fac-f5f6-4306-a837-855742abc702	en	{"static_content_body": "<p class=\\"ql-align-center\\">Powered by <a href=\\"https://kamicore.org\\" rel=\\"noopener noreferrer\\" target=\\"_blank\\">KamiCore</a></p>"}	draft	f	2026-09-04 18:26:47.653965+00
343	80229faa-4284-4d7c-90c8-b2403e906836	uk	{"title": "Статична сторінка"}	draft	f	2026-09-04 18:28:00.782025+00
344	d6365b6e-b6f6-4059-9dc7-8f0ec1269573	uk	{"menu_title": "Main menu for kamicode.org", "menu_description": "Displayed on front-end in top navigation section"}	draft	f	2026-09-06 02:22:54.535505+00
345	053b11e5-3a8a-47ae-a6e2-97bd0d321a3d	uk	{"item_title": "Головна"}	draft	f	2026-09-06 02:22:54.537554+00
346	422800ad-bfec-4b87-a473-e10238ced719	uk	{"item_title": "Про Kami"}	draft	f	2026-09-06 02:22:54.539811+00
347	558821bc-781a-464e-a680-6e2a9e323edd	uk	{"item_title": "Про все"}	draft	f	2026-09-06 02:22:54.540445+00
357	e78310dd-6c41-436b-b0a7-ec91fdd9a1f7	uk	{"tags": ["interior"], "summary": "Щоб поліпшити робоче місце, не завжди потрібні нові меблі чи повне перепланування. Часто достатньо кількох невеликих змін, щоб зробити його комфортнішим і зручнішим у використанні.", "article_body": "<h2>Приберіть те, що вам не потрібно</h2><p>Тримайте речі, якими користуєтеся регулярно, під рукою, а все інше приберіть з дороги. Трохи більше вільного простору допоможе зменшити відволікаючі фактори на робочому місці.</p><h2>Покращіть освітлення</h2><p>Хороше освітлення полегшує тривалу роботу. По можливості використовуйте природне світло, а додаткове освітлення розміщуйте там, де воно буде корисним, не створюючи відблисків чи різких тіней.</p><h2>Залиште місце для змін</h2><p>Робоче місце не має бути остаточно сформованим. Залишайте достатньо гнучкості, щоб переставляти речі, додавати щось корисне або прибирати те, що вам більше не підходить.</p><p>Невеликі зміни легко внести, легко скасувати, і часто їх достатньо, щоб помітно змінити атмосферу приміщення.</p>", "display_title": "Три невеликі зміни"}	draft	f	2026-09-06 01:57:49.06509+00
362	96de770c-a18f-4718-ac80-484075827ead	uk	{"static_content_body": "<h3>Інший варіант оформлення</h3><p>Для одного й того ж вмісту можна використовувати інший шаблон, не змінюючи дані, що зберігаються в блоках.</p>"}	draft	f	2026-09-04 10:47:28.802345+00
363	a659115d-b172-458e-974c-3e40d112d564	uk	{"static_content_body": "<h3>Частина сторінки</h3><p>Сторінка лише визначає, де ця група відображатиметься поряд з іншими екземплярами плагіна. Вміст та його оформлення залишаються окремими.</p>"}	draft	f	2026-09-04 10:48:27.318315+00
364	2d545e1a-af53-4df0-8377-97234528c8b2	uk	{"static_content_body": "<h1>Статична сторінка</h1><h2>Різні шаблони для блоків вмісту</h2><p>Блок вмісту може використовувати будь-який шаблон, наданий його плагіном або замінений поточною темою. Зміна шаблону змінює спосіб відображення вмісту, не змінюючи сам вміст.</p>"}	draft	f	2026-09-04 10:40:25.053864+00
365	53813421-b7aa-4454-b104-13988872b244	uk	{"static_content_body": "<p>Тут нічого не вимагає використання спеціального типу сторінки. Ця сторінка просто складена з блоків контенту, що можна використовувати повторно, групи та кількох шаблонів.</p>"}	draft	f	2026-09-04 10:48:54.454954+00
366	9ba25fe2-b9b9-4060-a200-fc286302c949	en	{"title": "Compound", "description": "Embedded structured value owned by its parent content item."}	draft	f	2026-09-11 00:00:00+00
367	9ba25fe2-b9b9-4060-a200-fc286302c949	uk	{"title": "Складене поле", "description": "Вбудоване структуроване значення, яке повністю належить батьківському елементу контенту."}	draft	f	2026-09-11 00:00:00+00
368	10ac77fc-162f-40b5-b82a-bfcda3b9a3c0	en	{"title":"Breadcrumbs","settings":{"separator":{"title":"Breadcrumb separator","description":"Text displayed between breadcrumb items."}},"handlers":{"view":{"title":"Breadcrumbs","description":"Render breadcrumb navigation in a page wrapper.","actions":{"renderPlaceholder":{"title":"Render placeholder","description":"Insert a placeholder that is resolved after breadcrumb finalization."}}}},"phrases":{"home":"Home"},"description":"Build and render breadcrumb navigation for the current page and routed content."}	draft	f	2026-09-12 00:00:00+00
369	10ac77fc-162f-40b5-b82a-bfcda3b9a3c0	uk	{"title":"Хлібні крихти","settings":{"separator":{"title":"Розділювач хлібних крихт","description":"Текст, що відображається між елементами навігаційного шляху."}},"handlers":{"view":{"title":"Хлібні крихти","description":"Відображення навігаційного шляху у wrapper сторінки.","actions":{"renderPlaceholder":{"title":"Вивести заповнювач","description":"Додає заповнювач, який замінюється після фінального формування хлібних крихт."}}}},"phrases":{"home":"Головна"},"description":"Побудова й відображення навігаційного шляху для поточної сторінки та routed-контенту."}	draft	f	2026-09-12 00:00:00+00
\.


--
-- Data for Name: user_auth_identities; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.user_auth_identities (id, user_id, provider, provider_user_id, provider_email, created_at, last_used_at) FROM stdin;
\.


--
-- Data for Name: user_messages; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.user_messages (message_id, user_id, message_date, message_text, viewed) FROM stdin;
\.


--
-- Data for Name: user_messages_old; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.user_messages_old (user_id, message_date, message_text) FROM stdin;
\.


--
-- Data for Name: user_profiles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.user_profiles (user_id, profile_data) FROM stdin;
\.


--
-- Name: api_tokens_token_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.api_tokens_token_id_seq', 1, false);


--
-- Name: content_acl_content_acl_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.content_acl_content_acl_id_seq', 21, true);


--
-- Name: content_items_item_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.content_items_item_id_seq', 45, true);


--
-- Name: content_types_ct_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.content_types_ct_id_seq', 7, true);


--
-- Name: domains_domain_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.domains_domain_id_seq', 1, true);


--
-- Name: field_options_option_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.field_options_option_id_seq', 1, false);


--
-- Name: field_types_type_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.field_types_type_id_seq', 31, true);


--
-- Name: field_variants_variant_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.field_variants_variant_id_seq', 19, true);


--
-- Name: fields_field_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.fields_field_id_seq', 30, true);


--
-- Name: item_bools_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.item_bools_id_seq', 1, false);


--
-- Name: item_dates_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.item_dates_id_seq', 3, true);


--
-- Name: item_nums_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.item_nums_id_seq', 5, true);


--
-- Name: item_texts_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.item_texts_id_seq', 63, true);


--
-- Name: logs_rec_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.logs_rec_id_seq', 1, false);


--
-- Name: media_media_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.media_media_id_seq', 1, false);


--
-- Name: mime_groups_mime_group_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.mime_groups_mime_group_id_seq', 1, false);


--
-- Name: notification_messages_notification_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.notification_messages_notification_id_seq', 1, false);


--
-- Name: pages_page_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.pages_page_id_seq', 17, true);


--
-- Name: pgm_recipes_recipe_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.pgm_recipes_recipe_id_seq', 3, true);


--
-- Name: plugin_acl_plugin_acl_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.plugin_acl_plugin_acl_id_seq', 31, true);


--
-- Name: plugin_endpoints_endpoint_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.plugin_endpoints_endpoint_id_seq', 1, true);


--
-- Name: plugins_plugin_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.plugins_plugin_id_seq', 22, true);


--
-- Name: pm_setup_history_setup_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.pm_setup_history_setup_id_seq', 22, true);


--
-- Name: pm_setup_resources_setup_resource_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.pm_setup_resources_setup_resource_id_seq', 9, true);


--
-- Name: secrets_secret_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.secrets_secret_id_seq', 1, false);


--
-- Name: theme_layouts_layout_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.theme_layouts_layout_id_seq', 4, true);


--
-- Name: themes_theme_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.themes_theme_id_seq', 1, true);


--
-- Name: tokens_2fa_token_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.tokens_2fa_token_id_seq', 1, false);


--
-- Name: translations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.translations_id_seq', 369, true);


--
-- Name: user_auth_identities_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.user_auth_identities_id_seq', 1, false);


--
-- Name: user_messages_message_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.user_messages_message_id_seq', 1, false);


--
-- Name: user_messages_user_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.user_messages_user_id_seq', 1, false);


--
-- Name: usergroups_usergroup_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.usergroups_usergroup_id_seq', 4, true);


--
-- Name: users_user_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.users_user_id_seq', 1, true);


--
-- PostgreSQL database dump complete
--

\unrestrict UdlipeCueW0umNZJhL9p7g4U4KGp2nyEVWD9IIcHwg6c0netGWpjIPRylDYf2cM

