#define FFI_SCOPE "sqlite"
#define FFI_LIB "libsqlite3.so.0"

typedef struct sqlite3 sqlite3;
typedef struct sqlite3_stmt sqlite3_stmt;

const char *sqlite3_libversion(void);

const char *sqlite3_errstr(int);
const char *sqlite3_errmsg(sqlite3*);

int sqlite3_open(const char *filename, sqlite3 **db);
int sqlite3_close(sqlite3*);

void sqlite3_free(void*);

int sqlite3_exec(sqlite3 *db, const char *sql, void *fn, void *fn_ctx, char **errmsg);

int sqlite3_prepare_v2(sqlite3 *db, const char *sql, int sql_len, sqlite3_stmt **stmt, const char **sql_tail);
sqlite3 *sqlite3_db_handle(sqlite3_stmt*);
int sqlite3_step(sqlite3_stmt*);
int sqlite3_finalize(sqlite3_stmt*);

int sqlite3_column_count(sqlite3_stmt*);

int sqlite3_data_count(sqlite3_stmt*);
const char *sqlite3_column_name(sqlite3_stmt*, int col);
int64_t sqlite3_column_int64(sqlite3_stmt*, int col);
double sqlite3_column_double(sqlite3_stmt*, int col);
const char *sqlite3_column_text(sqlite3_stmt*, int col);
void *sqlite3_column_blob(sqlite3_stmt*, int col);
int sqlite3_column_type(sqlite3_stmt*, int col);
int sqlite3_column_bytes(sqlite3_stmt*, int col);

int sqlite3_bind_int64(sqlite3_stmt*, int param_index, int64_t value);
int sqlite3_bind_double(sqlite3_stmt*, int param_index, double value);
int sqlite3_bind_text(sqlite3_stmt*, int param_index, const char *value, int len, int64_t destructor_kind);
int sqlite3_bind_blob(sqlite3_stmt*, int param_index, const char *value, int len, int64_t destructor_kind);
int sqlite3_bind_null(sqlite3_stmt*, int param_index);
int sqlite3_bind_parameter_index(sqlite3_stmt*, const char *param_name);
int sqlite3_reset(sqlite3_stmt*);
int sqlite3_clear_bindings(sqlite3_stmt*);
