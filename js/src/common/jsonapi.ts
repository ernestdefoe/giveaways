/** A JSON:API resource as the server sends it. */
export interface Resource<T extends { id: unknown }> {
  type?: string;
  id: string;
  attributes: Omit<T, 'id'>;
}

/**
 * The pages use resources flat: the attributes with the id beside them, a
 * number unless the type keeps it as the server's string.
 */
export function flat<T extends { id: number | string }>(r: Resource<T>, id: (raw: string) => T['id'] = Number as (raw: string) => T['id']): T {
  return { id: id(r.id), ...r.attributes } as T;
}
