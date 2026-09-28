-- 012: ADDITIVE. An agent's first contact with Projects is usually a READ (its tools on the records
-- server) — before any action token has reached PHP. The read servers hold no write grant, so this
-- one definer function is their only pen: once the kernel's run-facts call has vouched for the agent
-- (a valid run, this application's endpoints among its grants), the mirror records its admission.
-- It touches nothing but the capability of an active AGENT row that has none; a person is never
-- admitted this way (their capability comes with the hand-off token's claims). Copied from HR's db/011.
BEGIN;
CREATE OR REPLACE FUNCTION mcp_admit_agent(p_member_id bigint) RETURNS boolean
    LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
DECLARE n integer;
BEGIN
    UPDATE members SET capability = 'write', synced_at = now()
     WHERE id = p_member_id AND member_kind = 'agent' AND status = 'active' AND capability IS NULL;
    GET DIAGNOSTICS n = ROW_COUNT;
    RETURN n = 1;
END$$;
GRANT EXECUTE ON FUNCTION mcp_admit_agent(bigint) TO projects_records_ro, projects_activity_ro, projects_rw;
COMMIT;
