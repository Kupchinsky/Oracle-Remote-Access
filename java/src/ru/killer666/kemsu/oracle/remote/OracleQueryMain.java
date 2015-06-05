package ru.killer666.kemsu.oracle.remote;

import java.io.BufferedReader;
import java.io.IOException;
import java.io.InputStreamReader;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.ResultSet;
import java.sql.ResultSetMetaData;
import java.sql.SQLException;
import java.sql.Statement;
import java.text.Format;
import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Date;
import java.util.Iterator;
import java.util.List;

import com.google.gson.Gson;
import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.google.gson.JsonPrimitive;

public class OracleQueryMain
{
	public static String createMap(List<_QueryData> queriesResults)
	{
		JsonArray jsonArray = new JsonArray();

		Iterator<_QueryData> iter = queriesResults.iterator();

		while (iter.hasNext())
		{
			_QueryData data = iter.next();
			JsonObject childObject = new JsonObject();

			childObject.addProperty("query", data.query);
			childObject.add("fields", data.fields);
			childObject.add("data", data.data);

			jsonArray.add(childObject);
		}

		return jsonArray.toString();
	}

	public static void main(String[] args) throws SQLException, IOException
	{
		Gson gson = new Gson();

		BufferedReader br = new BufferedReader(new InputStreamReader(System.in));
		JsonArray queries = gson.fromJson(br.readLine(), JsonArray.class);

		DriverManager.registerDriver(new oracle.jdbc.driver.OracleDriver());
		Connection conn = DriverManager.getConnection(Config.connectionLine, "stud" + args[0], "stud" + args[0]);
		Statement stmt = conn.createStatement();

		List<_QueryData> queriesResults = new ArrayList<_QueryData>();
		Iterator<JsonElement> iter = queries.iterator();

		while (iter.hasNext())
		{
			_QueryData data = new _QueryData();
			data.query = iter.next().getAsString();

			try
			{
				data.rset = stmt.executeQuery(data.query);
			}
			catch (SQLException e)
			{
				data.queryEx = e;
			}

			try
			{
				if (data.rset != null)
					data.rsmd = data.rset.getMetaData();
			}
			catch (SQLException e)
			{
				data.queryEx = e;
			}

			if (data.rsmd != null)
			{
				int numberOfColumns = data.rsmd.getColumnCount();

				for (int i = 1; i <= numberOfColumns; i++)
					data.fields.add(new JsonPrimitive(data.rsmd.getColumnName(i) + ": "
							+ data.rsmd.getColumnTypeName(i)));

				while (data.rset.next())
				{
					JsonArray result = new JsonArray();
					data.data.add(result);

					for (int i = 1; i <= numberOfColumns; i++)
					{
						String type = data.rsmd.getColumnTypeName(i);

						if (type.equalsIgnoreCase("VARCHAR2"))
						{
							String out = data.rset.getString(i);
							result.add(new JsonPrimitive(data.rset.wasNull() ? "null" : out));
						}
						else if (type.equalsIgnoreCase("NUMBER"))
							result.add(new JsonPrimitive(data.rset.getInt(i)));
						else if (type.equalsIgnoreCase("DATE"))
						{
							Date out = data.rset.getDate(i);
							Format formatter = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss");
							result.add(new JsonPrimitive(data.rset.wasNull() ? "null" : formatter.format(out)));
						}
						else result.add(new JsonPrimitive("Unknown type"));
					}
				}
			}
			else
			{
				JsonArray result = new JsonArray();

				data.fields.add(new JsonPrimitive("Result"));
				result.add(new JsonPrimitive(data.queryEx == null ? "Unknown error" : (data.queryEx
						.getLocalizedMessage().contains("no statement parsed") ? "Success. No result" : data.queryEx.getClass()
						.getName() + ": " + data.queryEx.getLocalizedMessage())));
				data.data.add(result);
			}

			queriesResults.add(data);
		}

		System.out.println(createMap(queriesResults));
	}

	static class _QueryData
	{
		Exception			queryEx	= null;
		ResultSet			rset	= null;
		ResultSetMetaData	rsmd	= null;
		JsonArray			fields	= new JsonArray();
		JsonArray			data	= new JsonArray();
		String				query	= null;
	}
}
